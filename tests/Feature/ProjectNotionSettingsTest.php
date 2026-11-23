<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Jobs\SyncIssueToNotionJob;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const DB_HEX = '0123456789abcdef0123456789abcdef';

beforeEach(function () {
    configureNotion();
    fakeNotion();
    $this->project = Project::factory()->create(['key' => 'MIR']);
    $this->admin = member($this->project);
});

function quietIssue(Project $project, string $title): Issue
{
    return Issue::withoutEvents(fn () => (new CreateIssueAction)->handle($project, $title, IssueType::Feature));
}

function linkedProject(): Project
{
    return Project::query()->where('key', 'MIR')->firstOrFail();
}

it('links a database: stores the normalised id, the data source and the title column', function () {
    $this->actingAs($this->admin)
        ->put('/projects/MIR/notion', ['database' => 'https://www.notion.so/acme/Tasks-'.strtoupper(DB_HEX).'?v=fedcba9876543210fedcba9876543210'])
        ->assertRedirect('/projects/MIR/notion')
        ->assertSessionHasNoErrors();

    $project = linkedProject();
    expect($project->notion_database_id)->toBe(DB_HEX)
        ->and($project->notion_data_source_id)->toBe('ds-1')
        ->and($project->notion_title_property)->toBe('Name');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.notion.com/v1/databases/'.DB_HEX);
});

it('accepts a dashed id and shows it back as the stored form', function () {
    $this->actingAs($this->admin)
        ->put('/projects/MIR/notion', ['database' => '01234567-89ab-cdef-0123-456789abcdef'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get('/projects/MIR/notion')
        ->assertInertia(fn ($page) => $page
            ->component('projects/Notion')
            ->where('project', ['key' => 'MIR', 'name' => $this->project->name])
            ->where('notion.databaseId', DB_HEX)
            ->where('notion.linked', true));
});

it('rejects input that is not a database id without calling Notion', function (mixed $input) {
    $this->actingAs($this->admin)
        ->put('/projects/MIR/notion', ['database' => $input])
        ->assertSessionHasErrors('database');

    Http::assertNothingSent();
    expect(linkedProject()->notion_database_id)->toBeNull();
})->with([
    'missing' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'too short' => ['abc123'],
    'a foreign url' => ['https://evil.example/'.DB_HEX],
    'very long' => [str_repeat('a', 100000)],
    'unicode' => ['データベース'],
    'an array' => [[DB_HEX]],
    'an integer' => [12345],
]);

it('reports Notion refusing the database as a form error and stores nothing', function (string $route, int $status, array $body, string $message) {
    fakeNotion([$route => Http::response($body, $status)]);

    $this->actingAs($this->admin)
        ->put('/projects/MIR/notion', ['database' => DB_HEX])
        ->assertSessionHasErrors(['database' => $message]);

    expect(linkedProject()->notion_database_id)->toBeNull()
        ->and(linkedProject()->notion_data_source_id)->toBeNull();
})->with([
    'not shared' => ['GET /v1/databases/*', 404, ['code' => 'object_not_found', 'message' => 'x'], 'Notion could not find that database. Share the database with the integration and try again.'],
    'several data sources' => ['GET /v1/databases/*', 200, ['data_sources' => [['id' => 'a'], ['id' => 'b']]], 'Only a Notion database with exactly one data source can be linked.'],
    'zero data sources' => ['GET /v1/databases/*', 200, ['data_sources' => []], 'Only a Notion database with exactly one data source can be linked.'],
    'wrong column type' => ['GET /v1/data_sources/*', 200, ['properties' => ['Name' => ['type' => 'title'], 'Key' => ['type' => 'number']]], 'The "Key" column must be a rich text column, not number. Rename or remove it in Notion and try again.'],
    'bad token' => ['GET /v1/databases/*', 401, ['code' => 'unauthorized', 'message' => 'API token is invalid.'], 'Notion responded with 401 (unauthorized): API token is invalid.'],
    'rate limited' => ['GET /v1/databases/*', 429, ['code' => 'rate_limited', 'message' => 'slow'], 'Notion responded with 429 (rate_limited): slow'],
]);

it('never puts the token on a page or in a response', function () {
    $this->actingAs($this->admin)->put('/projects/MIR/notion', ['database' => DB_HEX]);

    $response = $this->actingAs($this->admin)->get('/projects/MIR/notion');

    expect($response->getContent())->not->toContain('secret_test_token');
});

it('keeps issue page ids when the same database is linked again, and drops them for another', function () {
    $this->actingAs($this->admin)->put('/projects/MIR/notion', ['database' => DB_HEX]);
    $issue = quietIssue(linkedProject(), 'One');
    $issue->forceFill(['notion_page_id' => 'page-1'])->saveQuietly();

    $this->actingAs($this->admin)->put('/projects/MIR/notion', ['database' => DB_HEX]);
    expect($issue->fresh()->notion_page_id)->toBe('page-1');

    $this->actingAs($this->admin)->put('/projects/MIR/notion', ['database' => str_repeat('b', 32)]);
    expect($issue->fresh()->notion_page_id)->toBeNull();
});

it('does not touch page ids of issues in other projects when relinking', function () {
    $other = Project::factory()->create(['key' => 'OTH']);
    $theirs = quietIssue($other, 'Theirs');
    $theirs->forceFill(['notion_page_id' => 'page-9'])->saveQuietly();
    $this->project->forceFill(['notion_database_id' => str_repeat('c', 32)])->save();

    $this->actingAs($this->admin)->put('/projects/MIR/notion', ['database' => DB_HEX]);

    expect($theirs->fresh()->notion_page_id)->toBe('page-9');
});

it('unlinks without calling Notion and leaves the rows and page ids alone', function () {
    $this->actingAs($this->admin)->put('/projects/MIR/notion', ['database' => DB_HEX]);
    $issue = quietIssue(linkedProject(), 'One');
    Http::fake();

    $this->actingAs($this->admin)
        ->delete('/projects/MIR/notion')
        ->assertRedirect('/projects/MIR/notion');

    Http::assertNothingSent();
    $project = linkedProject();
    expect($project->notion_database_id)->toBeNull()
        ->and($project->notion_data_source_id)->toBeNull()
        ->and($project->notion_title_property)->toBeNull();
});

it('syncs the issues that are not in Notion yet, skipping mirrored and archived ones', function () {
    $project = linkToNotion($this->project);
    $admin = $this->admin;
    $fresh = quietIssue($project, 'Fresh');
    $mirrored = quietIssue($project, 'Mirrored');
    $mirrored->forceFill(['notion_page_id' => 'p'])->saveQuietly();
    $archived = quietIssue($project, 'Archived');
    $archived->forceFill(['archived_at' => now()])->saveQuietly();
    $elsewhere = quietIssue(Project::factory()->create(), 'Elsewhere');
    Queue::fake();

    $this->actingAs($admin)
        ->post('/projects/MIR/notion/sync')
        ->assertRedirect('/projects/MIR/notion');

    Queue::assertPushed(SyncIssueToNotionJob::class, 1);
    Queue::assertPushed(SyncIssueToNotionJob::class, fn (SyncIssueToNotionJob $job) => $job->issue->is($fresh));
    expect($elsewhere->notion_page_id)->toBeNull();
});

it('staggers a backfill so it stays under the Notion rate limit', function () {
    $project = linkToNotion($this->project);
    $admin = $this->admin;
    foreach (range(1, 3) as $i) {
        quietIssue($project, "Issue {$i}");
    }
    Queue::fake();

    $this->actingAs($admin)->post('/projects/MIR/notion/sync');

    $delays = [];
    Queue::assertPushed(SyncIssueToNotionJob::class, function (SyncIssueToNotionJob $job) use (&$delays) {
        $delays[] = (int) now()->diffInMilliseconds($job->delay, absolute: true);

        return true;
    });
    sort($delays);
    expect($delays[1] - $delays[0])->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(400);
});

it('refuses to sync a project that is not linked', function () {
    Queue::fake();

    $this->actingAs($this->admin)
        ->post('/projects/MIR/notion/sync')
        ->assertSessionHasErrors('database');

    Queue::assertNothingPushed();
});

it('backfills from the command and says how many it queued', function () {
    $project = linkToNotion($this->project);
    quietIssue($project, 'One');
    quietIssue($project, 'Two');
    Queue::fake();

    $this->artisan('notion:backfill', ['project' => 'MIR'])
        ->expectsOutputToContain('Queued 2 issue(s) for Notion.')
        ->assertSuccessful();

    Queue::assertPushed(SyncIssueToNotionJob::class, 2);
});

it('fails the command for an unknown or unlinked project, or with the integration off', function () {
    Queue::fake();
    configureNotion();

    $this->artisan('notion:backfill', ['project' => 'NOPE'])->expectsOutputToContain('No project with key NOPE.')->assertFailed();
    $this->artisan('notion:backfill', ['project' => 'MIR'])->expectsOutputToContain('Project MIR is not linked to a Notion database.')->assertFailed();

    $this->project->forceFill(['notion_data_source_id' => 'ds-1', 'notion_title_property' => 'Name', 'notion_database_id' => DB_HEX])->save();
    config()->set('services.notion.api_key', '');
    $this->artisan('notion:backfill', ['project' => 'MIR'])->expectsOutputToContain('Notion is not configured.')->assertFailed();

    Queue::assertNothingPushed();
});

describe('who may touch it', function () {
    function notionRequests(): array
    {
        return [
            'view' => ['get', '/projects/MIR/notion'],
            'link' => ['put', '/projects/MIR/notion', ['database' => DB_HEX]],
            'unlink' => ['delete', '/projects/MIR/notion'],
            'sync' => ['post', '/projects/MIR/notion/sync'],
        ];
    }

    it('refuses everyone who is not a project admin, and changes nothing', function (string $who, string $verb, string $uri, array $payload = []) {
        $project = Project::query()->where('key', 'MIR')->firstOrFail();
        $project->forceFill(['notion_database_id' => str_repeat('d', 32), 'notion_data_source_id' => 'ds-keep', 'notion_title_property' => 'Name'])->save();
        $user = match ($who) {
            'a read member' => member($project, ProjectLevel::Read),
            'a write member' => member($project, ProjectLevel::Write),
            'an outsider' => User::factory()->create(),
            'a member of another project only' => member(Project::factory()->create()),
            'an organisation member' => (function () use ($project) {
                $user = User::factory()->create();
                $project->organization?->members()->attach($user->id, ['role' => OrganizationRole::Member->value]);

                return $user;
            })(),
        };
        Queue::fake();
        Http::fake();

        $this->actingAs($user)->{$verb}($uri, $payload)->assertForbidden();

        Http::assertNothingSent();
        Queue::assertNothingPushed();
        expect(linkedProject()->notion_data_source_id)->toBe('ds-keep');
    })->with(['a read member', 'a write member', 'an outsider', 'a member of another project only', 'an organisation member'])
        ->with(fn () => notionRequests());

    it('sends guests to log in', function (string $verb, string $uri, array $payload = []) {
        $this->{$verb}($uri, $payload)->assertRedirect('/login');
    })->with(fn () => array_values(notionRequests()));

    it('lets an organisation owner administer a project they have no direct grant on', function () {
        [$organization, $owner] = organizationWith(OrganizationRole::Owner);
        $project = Project::factory()->create(['key' => 'ORG', 'organization_id' => $organization->id]);

        $this->actingAs($owner)->put('/projects/ORG/notion', ['database' => DB_HEX])->assertSessionHasNoErrors();

        expect($project->fresh()->notion_data_source_id)->toBe('ds-1');
    });

    it('cannot link another project through the url or the body', function () {
        $victim = Project::factory()->create(['key' => 'VIC']);

        $this->actingAs($this->admin)->put('/projects/VIC/notion', ['database' => DB_HEX, 'project' => 'MIR'])->assertForbidden();

        expect($victim->fresh()->notion_data_source_id)->toBeNull();
    });

    it('does not mirror an issue of another project after a link', function () {
        $victim = Project::factory()->create(['key' => 'VIC']);
        $this->actingAs($this->admin)->put('/projects/MIR/notion', ['database' => DB_HEX]);
        Http::fake();

        (new CreateIssueAction)->handle($victim, 'Private', IssueType::Feature);

        Http::assertNothingSent();
        expect(Issue::query()->whereNotNull('notion_page_id')->count())->toBe(0);
    });
});

it('is hidden, as a 404 with no outbound call, when the token is blank', function (?string $value, string $verb, string $uri, array $payload = []) {
    config()->set('services.notion.api_key', $value);
    Http::fake();

    $this->actingAs($this->admin)->{$verb}($uri, $payload)->assertNotFound();

    Http::assertNothingSent();
})->with('blank')->with(fn () => array_values(notionRequests()));

it('shows the mirror counts on the settings page', function () {
    $project = linkToNotion($this->project);
    $admin = $this->admin;
    $one = quietIssue($project, 'One');
    $one->forceFill(['notion_page_id' => 'p'])->saveQuietly();
    Queue::fake();
    quietIssue($project, 'Two');

    $this->actingAs($admin)->get('/projects/MIR/notion')
        ->assertInertia(fn ($page) => $page
            ->where('notion.linked', true)
            ->where('notion.databaseId', DB_HEX)
            ->where('notion.mirrored', 1)
            ->where('notion.pending', 1)
            ->missing('notion.token')
            ->missing('notion.dataSourceId'));
});
