<?php

declare(strict_types=1);

use App\Actions\ArchiveDoneIssuesAction;
use App\Actions\CreateIssueAction;
use App\Actions\NotifyIssueWebhooksAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhookJob;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectWebhook;
use Illuminate\Support\Facades\Queue;

/**
 * Deliveries of one event to the endpoints of the project with this key.
 */
function deliveries(string $event, string $projectKey): int
{
    return Queue::pushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === $event
        && $job->payload['project'] === $projectKey)->count();
}

it('archives a done issue closed more than 24 hours ago', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subHours(25)])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(1)
        ->and($issue->fresh()->archived_at)->not->toBeNull();
});

it('does not archive a done issue closed less than 24 hours ago', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subHours(23)])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(0)
        ->and($issue->fresh()->archived_at)->toBeNull();
});

it('does not archive issues that are not done', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::InReview])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(0);
});

it('does not re-archive an already archived issue', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill([
        'status' => IssueStatus::Done,
        'closed_at' => now()->subHours(48),
        'archived_at' => now()->subHours(1),
    ])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(0);
});

it('excludes archived issues from the index and board', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $visible = (new CreateIssueAction)->handle($team, 'Visible issue', IssueType::Feature);
    $archived = (new CreateIssueAction)->handle($team, 'Archived issue', IssueType::Feature);
    $archived->forceFill(['status' => IssueStatus::Done, 'archived_at' => now()])->save();

    $user = member($team);

    $this->actingAs($user)->get('/issues')->assertInertia(fn ($page) => $page
        ->where('issues.0.identifier', $visible->identifier)
        ->has('issues', 1)
    );

    $this->actingAs($user)->get('/issues/board')->assertInertia(fn ($page) => $page
        ->where('issues.0.identifier', $visible->identifier)
        ->has('issues', 1)
    );
});

it('still shows an archived issue on its own detail page', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'Archived issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::Done, 'archived_at' => now()])->save();

    $this->actingAs(member($team))
        ->get("/issues/{$issue->identifier}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('issue.archivedAt', fn ($value) => $value !== null));
});

it("honours a project's custom archive duration", function () {
    $team = Project::factory()->create(['key' => 'THI', 'archive_after_days' => 7]);
    $recent = (new CreateIssueAction)->handle($team, 'Recent', IssueType::Feature);
    $recent->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(5)])->save();
    $old = (new CreateIssueAction)->handle($team, 'Old', IssueType::Feature);
    $old->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(8)])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(1)
        ->and($recent->fresh()->archived_at)->toBeNull()
        ->and($old->fresh()->archived_at)->not->toBeNull();
});

it('never archives issues of a project with a null archive duration', function () {
    $team = Project::factory()->create(['key' => 'THI', 'archive_after_days' => null]);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(100)])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(0)
        ->and($issue->fresh()->archived_at)->toBeNull();
});

it('records the archive on the issue timeline, with its reason', function () {
    $project = Project::factory()->create(['key' => 'THI', 'archive_after_days' => 7]);
    $issue = Issue::factory()->for($project)->create(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(8)]);

    (new ArchiveDoneIssuesAction)->handle();

    $activity = $issue->activities()->where('type', 'archived')->sole();

    expect($activity->data)->toBe(['reason' => 'Auto-archived 7 days after being done'])
        ->and($activity->user_id)->toBeNull();
});

it('tells an endpoint subscribed to archives about an auto-archived issue', function () {
    Queue::fake();
    $project = Project::factory()->create(['key' => 'THI']);
    ProjectWebhook::factory()->for($project)->create(['events' => [WebhookEvent::Archived->value]]);
    $issue = Issue::factory()->for($project)->create(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(2)]);

    (new ArchiveDoneIssuesAction)->handle();

    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === 'issue.archived'
        && $job->payload['issue']['identifier'] === $issue->identifier);
});

it('caps a large sweep and closes it with a summary of what was held back', function () {
    Queue::fake();
    $project = Project::factory()->create(['key' => 'THI']);
    ProjectWebhook::factory()->for($project)->create(['events' => [WebhookEvent::Archived->value]]);
    Issue::factory()->for($project)->count(NotifyIssueWebhooksAction::PER_REQUEST_CAP + 5)
        ->create(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(2)]);

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(NotifyIssueWebhooksAction::PER_REQUEST_CAP + 5)
        ->and(Issue::query()->whereNull('archived_at')->count())->toBe(0)
        ->and(deliveries('issue.archived', 'THI'))->toBe(NotifyIssueWebhooksAction::PER_REQUEST_CAP);

    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === 'issue.bulk_changed'
        && $job->payload['suppressed'] === 5);
});

it('gives each project its own delivery budget in a sweep', function () {
    Queue::fake();
    $quiet = Project::factory()->create(['key' => 'QUIET']);
    $busy = Project::factory()->create(['key' => 'BUSY']);

    foreach ([$quiet, $busy] as $project) {
        ProjectWebhook::factory()->for($project)->create(['events' => [WebhookEvent::Archived->value]]);
    }

    Issue::factory()->for($quiet)->create(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(2)]);
    Issue::factory()->for($busy)->count(NotifyIssueWebhooksAction::PER_REQUEST_CAP + 5)
        ->create(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(2)]);

    (new ArchiveDoneIssuesAction)->handle();

    expect(deliveries('issue.archived', 'QUIET'))->toBe(1)
        ->and(deliveries('issue.bulk_changed', 'QUIET'))->toBe(0)
        ->and(deliveries('issue.archived', 'BUSY'))->toBe(NotifyIssueWebhooksAction::PER_REQUEST_CAP);

    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === 'issue.bulk_changed'
        && $job->payload['project'] === 'BUSY'
        && $job->payload['suppressed'] === 5);
});
