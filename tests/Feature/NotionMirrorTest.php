<?php

declare(strict_types=1);

use App\Actions\ArchiveIssueAction;
use App\Actions\CreateIssueAction;
use App\Actions\MirrorIssueToNotionAction;
use App\Actions\SyncIssueToNotionAction;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Jobs\SyncIssueToNotionJob;
use App\Jobs\TrashNotionPageJob;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;
use App\Services\Notion;
use App\Services\NotionException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function mirrored(Project $project, string $title = 'Fix the login'): Issue
{
    return (new CreateIssueAction)->handle($project, $title, IssueType::Fix);
}

function pageWrites(string $method): array
{
    return Http::recorded(fn (Request $request) => $request->method() === $method && str_contains($request->url(), '/v1/pages'))
        ->map(fn (array $pair) => $pair[0]->data())
        ->values()
        ->all();
}

it('creates a Notion page when an issue is filed and remembers its id', function () {
    fakeNotion(['POST /v1/pages' => ['id' => 'page-42']]);
    $project = notionProject();

    $issue = mirrored($project);

    expect($issue->fresh()->notion_page_id)->toBe('page-42');
    [$body] = pageWrites('POST');
    expect($body['parent'])->toBe(['type' => 'data_source_id', 'data_source_id' => 'ds-1'])
        ->and($body['properties']['Name'])->toBe(['title' => [['text' => ['content' => 'Fix the login']]]])
        ->and($body['properties']['Key'])->toBe(['rich_text' => [['text' => ['content' => $issue->identifier]]]])
        ->and($body['properties']['Link'])->toBe(['url' => url("/issues/{$issue->identifier}")])
        ->and($body['properties']['Priority'])->toBe(['select' => ['name' => 'none']])
        ->and($body['properties']['State'])->toBe(['select' => ['name' => 'backlog']])
        ->and($body['properties']['Assignee'])->toBe(['rich_text' => []])
        ->and($body['properties']['Labels'])->toBe(['multi_select' => []])
        ->and($body)->not->toHaveKey('in_trash');
});

it('mirrors nothing when the project is not linked', function () {
    configureNotion();
    fakeNotion();

    $issue = mirrored(Project::factory()->create());

    Http::assertNothingSent();
    expect($issue->fresh()->notion_page_id)->toBeNull();
});

it('calls nobody when the integration is off, even for a linked project', function (?string $value) {
    fakeNotion();
    $project = notionProject();
    config()->set('services.notion.api_key', $value);

    mirrored($project);

    Http::assertNothingSent();
})->with('blank');

it('only mirrors the linked project, never another tenant', function () {
    fakeNotion();
    $linked = notionProject();
    $other = Project::factory()->create(['key' => 'OTH']);

    mirrored($other);

    Http::assertNothingSent();
    mirrored($linked);
    Http::assertSentCount(1);
});

it('updates the same page when the issue changes', function () {
    fakeNotion(['POST /v1/pages' => ['id' => 'page-42']]);
    $issue = mirrored(notionProject());
    $assignee = User::factory()->create(['name' => 'Ada Lovelace']);

    $issue->update(['title' => 'Renamed', 'priority' => IssuePriority::High, 'assignee_id' => $assignee->id]);
    $issue->forceFill(['status' => IssueStatus::InProgress])->save();

    $updates = pageWrites('PATCH');
    expect($updates)->toHaveCount(2);
    $last = end($updates);
    expect($last['properties']['Name']['title'][0]['text']['content'])->toBe('Renamed')
        ->and($last['properties']['Priority'])->toBe(['select' => ['name' => 'high']])
        ->and($last['properties']['State'])->toBe(['select' => ['name' => 'in_progress']])
        ->and($last['properties']['Assignee'])->toBe(['rich_text' => [['text' => ['content' => 'Ada Lovelace']]]])
        ->and($last['in_trash'])->toBeFalse();
    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && $request->url() === 'https://api.notion.com/v1/pages/page-42');
    Http::assertSentCount(3);
});

it('does not call Notion for changes that are not mirrored', function () {
    fakeNotion();
    $issue = mirrored(notionProject());

    $issue->update(['description' => 'New description', 'type' => IssueType::Feature]);

    Http::assertSentCount(1);
});

it('does not loop on saving the page id', function () {
    fakeNotion();

    mirrored(notionProject());

    Http::assertSentCount(1);
});

it('mirrors labels, sanitising commas and capping names', function () {
    fakeNotion();
    $project = notionProject();
    $issue = mirrored($project);
    $issue->syncLabelsWithActivity([
        Label::factory()->create(['name' => 'ui, ux'])->id,
        Label::factory()->create(['name' => str_repeat('é', 150)])->id,
        Label::factory()->create(['name' => 'Bug'])->id,
        Label::factory()->create(['name' => 'bug '])->id,
    ]);

    $last = lastWrite('PATCH');
    $names = array_column($last['properties']['Labels']['multi_select'], 'name');

    expect($names)->toHaveCount(3)
        ->and($names[0])->toBe('Bug')
        ->and($names[1])->toBe('ui ux')
        ->and(mb_strlen($names[2]))->toBe(100);
});

it('mirrors labels attached right after filing from a template', function () {
    fakeNotion();
    $issue = mirrored(notionProject());
    $label = Label::factory()->create(['name' => 'backend']);

    $issue->labels()->sync([$label->id]);
    app(MirrorIssueToNotionAction::class)->handle($issue);

    $last = lastWrite('PATCH');
    expect($last['properties']['Labels'])->toBe(['multi_select' => [['name' => 'backend']]]);
});

it('survives awkward titles', function (string $title, string $expected) {
    fakeNotion();

    mirrored(notionProject(), $title);

    [$body] = pageWrites('POST');
    expect($body['properties']['Name']['title'][0]['text']['content'])->toBe($expected);
})->with([
    'markdown stays literal' => ['**bold** [link](http://x) `code`', '**bold** [link](http://x) `code`'],
    'emoji' => ['Ship it 🚀', 'Ship it 🚀'],
    'over 2000 characters' => [str_repeat('é', 2500), str_repeat('é', 2000)],
]);

it('marks the Notion row trashed when the issue is archived and restores it', function () {
    fakeNotion(['POST /v1/pages' => ['id' => 'page-42']]);
    $issue = mirrored(notionProject());

    (new ArchiveIssueAction)->handle($issue, 'done');
    (new ArchiveIssueAction)->unarchive($issue->fresh());

    $updates = pageWrites('PATCH');
    expect(array_column($updates, 'in_trash'))->toBe([true, false]);
});

it('trashes the Notion row when the issue is deleted', function () {
    fakeNotion(['POST /v1/pages' => ['id' => 'page-42']]);
    $issue = mirrored(notionProject());

    $issue->fresh()->delete();

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && $request->url() === 'https://api.notion.com/v1/pages/page-42'
        && $request->data() === ['in_trash' => true]);
});

it('leaves Notion alone when an unmirrored issue is deleted', function () {
    configureNotion();
    fakeNotion();
    $issue = mirrored(Project::factory()->create());

    $issue->delete();

    Http::assertNothingSent();
});

it('stops syncing, and leaves the rows, once the project is unlinked', function () {
    fakeNotion(['POST /v1/pages' => ['id' => 'page-42']]);
    $project = notionProject();
    $issue = mirrored($project);
    $project->forceFill(['notion_database_id' => null, 'notion_data_source_id' => null, 'notion_title_property' => null])->save();

    $issue->fresh()->update(['title' => 'Later']);
    $issue->fresh()->delete();

    Http::assertSentCount(1);
});

it('queues the work instead of calling Notion inside the request, one waiting sync per issue', function () {
    Queue::fake();
    Http::fake();
    $project = notionProject();

    $issue = mirrored($project);
    $issue->update(['title' => 'Changed']);
    $other = mirrored($project, 'Another');

    Queue::assertPushed(SyncIssueToNotionJob::class, 2);
    Queue::assertNotPushed(TrashNotionPageJob::class);
    Http::assertNothingSent();
    expect($other->id)->not->toBe($issue->id);
});

it('queues a trash job for a mirrored issue that is deleted', function () {
    Queue::fake();
    $project = notionProject();
    $issue = mirrored($project);
    $issue->forceFill(['notion_page_id' => 'page-9'])->saveQuietly();

    $issue->delete();

    Queue::assertPushed(TrashNotionPageJob::class, fn (TrashNotionPageJob $job) => $job->pageId === 'page-9');
});

it('does not let a Notion failure block or fail the issue save', function () {
    fakeNotion(['POST /v1/pages' => Http::response(['object' => 'error', 'code' => 'validation_error', 'message' => 'nope'], 400)]);
    $project = notionProject();

    $issue = mirrored($project);

    expect($issue->exists)->toBeTrue()
        ->and(Issue::query()->count())->toBe(1);
});

it('records a failed sync on the issue timeline without the token', function () {
    fakeNotion(['POST /v1/pages' => Http::response(['object' => 'error', 'code' => 'validation_error', 'message' => 'Name is expected to be title.'], 400)]);
    $project = notionProject();
    $issue = mirrored($project);

    $job = new SyncIssueToNotionJob($issue);
    $job->failed(new NotionException('Notion responded with 400 (validation_error): Name is expected to be title.', 400));

    $activity = $issue->activities()->where('type', 'notion_sync_failed')->firstOrFail();
    expect($activity->data)->toBe(['message' => 'Notion responded with 400 (validation_error): Name is expected to be title.'])
        ->and(json_encode($activity->data))->not->toContain('secret_test_token');
});

it('records only a fixed message for errors that do not come from Notion', function () {
    configureNotion();
    $issue = mirrored(Project::factory()->create());

    (new SyncIssueToNotionJob($issue))->failed(new RuntimeException(str_repeat('x', 1000)));

    expect($issue->activities()->where('type', 'notion_sync_failed')->firstOrFail()->data['message'])->toBe('Unexpected error, see the failed jobs.');
});

it('releases the job for Retry-After seconds on a rate limit instead of failing', function () {
    fakeNotion(['POST /v1/pages' => Http::response(['object' => 'error', 'code' => 'rate_limited', 'message' => 'slow'], 429, ['Retry-After' => '9'])]);
    $project = notionProject();
    Queue::fake();
    $issue = mirrored($project);

    $job = new SyncIssueToNotionJob($issue);
    $job->setJob($queued = Mockery::mock(Job::class)->shouldIgnoreMissing());
    $queued->shouldReceive('release')->once()->with(9);

    $job->handle(app(SyncIssueToNotionAction::class));

    expect($issue->fresh()->notion_page_id)->toBeNull();
});

it('keeps retrying for half an hour and survives a few real errors', function () {
    configureNotion();
    $job = new SyncIssueToNotionJob(mirrored(Project::factory()->create()));

    expect($job->retryUntil()->diffInMinutes(now(), absolute: true))->toBeGreaterThan(29)->toBeLessThanOrEqual(30)
        ->and($job->maxExceptions)->toBe(3)
        ->and($job->backoff())->toBe([10, 60])
        ->and($job->uniqueId())->toBe((string) $job->issue->id)
        ->and($job->deleteWhenMissingModels)->toBeTrue();
});

it('is a no-op when the issue is no longer mirrorable by the time the job runs', function () {
    fakeNotion();
    $project = notionProject();
    Queue::fake();
    $issue = mirrored($project);
    $project->forceFill(['notion_database_id' => null, 'notion_data_source_id' => null, 'notion_title_property' => null])->save();

    (new SyncIssueToNotionJob($issue->fresh()))->handle(app(SyncIssueToNotionAction::class));

    Http::assertNothingSent();
});

it('ignores a trash for a page Notion no longer has', function () {
    fakeNotion(['PATCH /v1/pages/*' => Http::response(['object' => 'error', 'code' => 'object_not_found', 'message' => 'gone'], 404)]);
    configureNotion();

    (new TrashNotionPageJob('page-gone'))->handle(app(Notion::class));

    Http::assertSentCount(1);
});

it('lets other trash failures fail the job', function () {
    fakeNotion(['PATCH /v1/pages/*' => Http::response(['object' => 'error', 'code' => 'internal_server_error', 'message' => 'oops'], 500)]);
    configureNotion();

    expect(fn () => (new TrashNotionPageJob('page-1'))->handle(app(Notion::class)))
        ->toThrow(NotionException::class);
});

function lastWrite(string $method): array
{
    $writes = pageWrites($method);

    return $writes[array_key_last($writes)];
}

it('recreates the page when Notion no longer has it', function () {
    fakeNotion(['POST /v1/pages' => ['id' => 'page-new']]);
    $issue = mirrored(notionProject());
    $issue->fresh()->forceFill(['notion_page_id' => 'page-gone'])->saveQuietly();
    fakeNotion([
        'PATCH /v1/pages/page-gone' => Http::response(['code' => 'object_not_found', 'message' => 'gone'], 404),
        'POST /v1/pages' => ['id' => 'page-new'],
    ]);

    $issue->fresh()->update(['title' => 'Renamed']);

    expect($issue->fresh()->notion_page_id)->toBe('page-new');
});

it('does not recreate the page of an archived issue Notion no longer has', function () {
    fakeNotion();
    $issue = mirrored(notionProject());
    fakeNotion(['PATCH /v1/pages/*' => Http::response(['code' => 'object_not_found', 'message' => 'gone'], 404)]);

    expect(fn () => (new ArchiveIssueAction)->handle($issue->fresh()))->not->toThrow(Throwable::class);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('refuses a create response without a page id and stores nothing', function () {
    fakeNotion(['POST /v1/pages' => ['object' => 'page']]);

    $issue = mirrored(notionProject());

    expect($issue->fresh()->notion_page_id)->toBeNull();
});

it('does not bump updated_at when it stores the page id', function () {
    fakeNotion();
    $project = notionProject();
    $issue = Issue::withoutEvents(fn () => (new CreateIssueAction)->handle($project, 'Old', IssueType::Fix));
    Issue::query()->whereKey($issue->id)->toBase()->update(['updated_at' => '2020-01-01 00:00:00']);

    (new SyncIssueToNotionJob($issue->fresh()))->handle(app(SyncIssueToNotionAction::class));

    expect($issue->fresh()->updated_at->year)->toBe(2020)->and($issue->fresh()->notion_page_id)->toBe('page-1');
});
