<?php

declare(strict_types=1);

use App\Actions\AddCommentAction;
use App\Actions\BulkUpdateIssuesAction;
use App\Actions\NotifyIssueWebhooksAction;
use App\Enums\IssueStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhookJob;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectWebhook;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * Deliveries of one event, optionally only those to one project's endpoints.
 */
function webhookDeliveries(string $event, ?string $projectKey = null): int
{
    return Queue::pushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === $event
        && ($projectKey === null || $job->payload['project'] === $projectKey))->count();
}

/**
 * The suppressed count of each summary queued for one project, in order.
 *
 * @return list<int>
 */
function webhookSummaries(string $projectKey): array
{
    return Queue::pushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === 'issue.bulk_changed'
        && $job->payload['project'] === $projectKey)
        ->map(fn (DeliverWebhookJob $job): mixed => $job->payload['suppressed'])
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function backlogIssues(Project $project, int $count): array
{
    return Issue::factory()->for($project)->count($count)->create()->pluck('identifier')->all();
}

/**
 * @param  list<string>  $identifiers
 */
function bulkClose(array $identifiers, User $actor): void
{
    app(BulkUpdateIssuesAction::class)->handle($identifiers, ['status' => 'done'], $actor);
}

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
});

it('still sends only status changes to an endpoint that predates events', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create(['events' => null]);

    $issue = Issue::factory()->for($this->project)->create();
    Queue::assertNothingPushed();

    $issue->forceFill(['status' => IssueStatus::Done])->save();
    Queue::assertPushed(DeliverWebhookJob::class, 1);
});

it('sends a subscribed event', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::Created->value],
    ]);

    Issue::factory()->for($this->project)->create();

    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === 'issue.created');
});

it('fires on assignment, archive and restore when subscribed', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [
            WebhookEvent::Assigned->value,
            WebhookEvent::Archived->value,
            WebhookEvent::Restored->value,
        ],
    ]);
    $issue = Issue::factory()->for($this->project)->create();

    $issue->forceFill(['assignee_id' => $this->user->id])->save();
    $issue->forceFill(['archived_at' => now()])->save();
    $issue->forceFill(['archived_at' => null])->save();

    foreach (['issue.assigned', 'issue.archived', 'issue.restored'] as $event) {
        Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === $event);
    }
});

it('fires on a comment when subscribed', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::Commented->value],
    ]);
    $issue = Issue::factory()->for($this->project)->create();

    app(AddCommentAction::class)->handle($issue, $this->user, 'Something to say');

    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === 'issue.commented');
});

it('does not send an event the endpoint did not ask for', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::StatusChanged->value],
    ]);

    Issue::factory()->for($this->project)->create();

    Queue::assertNothingPushed();
});

it('caps deliveries in a batch and closes it with a summary of what was held back', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::Created->value],
    ]);

    NotifyIssueWebhooksAction::batch(fn () => backlogIssues($this->project, NotifyIssueWebhooksAction::PER_BATCH_CAP + 10));

    expect(webhookDeliveries('issue.created'))->toBe(NotifyIssueWebhooksAction::PER_BATCH_CAP)
        ->and(webhookSummaries('THI'))->toBe([10]);
});

it('sends the summary as an event of its own', function () {
    Queue::fake();
    $this->freezeTime();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::Created->value],
    ]);

    NotifyIssueWebhooksAction::batch(fn () => backlogIssues($this->project, NotifyIssueWebhooksAction::PER_BATCH_CAP + 1));

    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === 'issue.bulk_changed'
        && $job->payload === [
            'event' => 'issue.bulk_changed',
            'project' => 'THI',
            'suppressed' => 1,
            'sent_at' => now()->toIso8601String(),
        ]);
});

it('sends no summary when a batch stays within the cap', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::Created->value],
    ]);

    NotifyIssueWebhooksAction::batch(fn () => backlogIssues($this->project, NotifyIssueWebhooksAction::PER_BATCH_CAP));

    expect(webhookDeliveries('issue.created'))->toBe(NotifyIssueWebhooksAction::PER_BATCH_CAP)
        ->and(webhookSummaries('THI'))->toBe([]);
});

it('hands back what the batched work returns', function () {
    expect(NotifyIssueWebhooksAction::batch(fn (): string => 'done'))->toBe('done');
});

// A queue worker, the scheduler or Octane keeps one process alive across many
// units of work. Nothing outside a batch may count towards a cap, or a
// long-lived process would eventually stop delivering without saying so.
it('does not cap changes made outside a batch, however many one process makes', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::Created->value],
    ]);

    backlogIssues($this->project, NotifyIssueWebhooksAction::PER_BATCH_CAP + 10);

    expect(webhookDeliveries('issue.created'))->toBe(NotifyIssueWebhooksAction::PER_BATCH_CAP + 10)
        ->and(webhookSummaries('THI'))->toBe([]);
});

it('keeps delivering after a bulk change that filled the cap without passing it', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::StatusChanged->value],
    ]);
    bulkClose(backlogIssues($this->project, NotifyIssueWebhooksAction::PER_BATCH_CAP), $this->user);

    $later = Issue::factory()->for($this->project)->create();
    $later->forceFill(['status' => IssueStatus::Done])->save();

    expect(webhookDeliveries('issue.status_changed'))->toBe(NotifyIssueWebhooksAction::PER_BATCH_CAP + 1);
});

it('does not swallow a second run past the cap in the same process', function () {
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::StatusChanged->value],
    ]);

    foreach ([1, 2] as $round) {
        Queue::fake();
        bulkClose(backlogIssues($this->project, NotifyIssueWebhooksAction::PER_BATCH_CAP + 3), $this->user);

        expect(webhookDeliveries('issue.status_changed'))->toBe(NotifyIssueWebhooksAction::PER_BATCH_CAP)
            ->and(webhookSummaries('THI'))->toBe([3]);
    }

    Queue::fake();
    $closed = Issue::factory()->for($this->project)->count(NotifyIssueWebhooksAction::PER_BATCH_CAP + 3)->create();
    $closed->each(fn (Issue $issue) => $issue->forceFill(['status' => IssueStatus::Done])->save());

    expect(webhookDeliveries('issue.status_changed'))->toBe(NotifyIssueWebhooksAction::PER_BATCH_CAP + 3);
});

it('summarises each project of a bulk change to its own endpoints', function () {
    Queue::fake();
    $other = Project::factory()->create(['key' => 'OTH']);
    joinProjects($this->user, $other);

    foreach ([$this->project, $other] as $project) {
        ProjectWebhook::factory()->for($project)->create(['events' => [WebhookEvent::StatusChanged->value]]);
    }

    bulkClose([
        ...backlogIssues($this->project, NotifyIssueWebhooksAction::PER_BATCH_CAP),
        ...backlogIssues($other, 3),
    ], $this->user);

    expect(webhookDeliveries('issue.status_changed', 'THI'))->toBe(NotifyIssueWebhooksAction::PER_BATCH_CAP)
        ->and(webhookDeliveries('issue.status_changed', 'OTH'))->toBe(0)
        ->and(webhookSummaries('THI'))->toBe([])
        ->and(webhookSummaries('OTH'))->toBe([3]);
});

it('leaves no cap behind when a bulk change fails halfway', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::StatusChanged->value],
    ]);
    $identifiers = backlogIssues($this->project, NotifyIssueWebhooksAction::PER_BATCH_CAP + 2);

    $failing = true;
    $saves = 0;
    Issue::updated(function () use (&$failing, &$saves): void {
        if ($failing && ++$saves === NotifyIssueWebhooksAction::PER_BATCH_CAP + 2) {
            throw new RuntimeException('Disk full');
        }
    });

    expect(fn () => bulkClose($identifiers, $this->user))->toThrow(RuntimeException::class, 'Disk full');

    $failing = false;
    Queue::fake();
    $later = Issue::factory()->for($this->project)->create();
    $later->forceFill(['status' => IssueStatus::Done])->save();

    expect(webhookDeliveries('issue.status_changed'))->toBe(1);
});

it('shares one budget and one summary between a batch and a batch opened inside it', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::Created->value],
    ]);

    NotifyIssueWebhooksAction::batch(function (): void {
        NotifyIssueWebhooksAction::batch(fn () => backlogIssues($this->project, NotifyIssueWebhooksAction::PER_BATCH_CAP + 1));

        expect(webhookSummaries('THI'))->toBe([]);

        backlogIssues($this->project, 2);
    });

    expect(webhookDeliveries('issue.created'))->toBe(NotifyIssueWebhooksAction::PER_BATCH_CAP)
        ->and(webhookSummaries('THI'))->toBe([3]);
});

it('lets an endpoint choose its events from settings', function () {
    $admin = member($this->project);
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    $this->actingAs($admin)->patch("/projects/THI/webhooks/{$webhook->id}", [
        'active' => true,
        'events' => ['issue.created', 'issue.commented'],
    ])->assertRedirect();

    expect($webhook->fresh()->subscribedEvents())->toBe(['issue.created', 'issue.commented']);
});

it('discards an unknown event name rather than storing one that never fires', function () {
    $admin = member($this->project);
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    $this->actingAs($admin)->patch("/projects/THI/webhooks/{$webhook->id}", [
        'active' => true,
        'events' => ['issue.created', 'issue.invented'],
    ]);

    expect($webhook->fresh()->subscribedEvents())->toBe(['issue.created']);
});

it('leaves events alone when the request does not mention them', function () {
    $admin = member($this->project);
    $webhook = ProjectWebhook::factory()->for($this->project)->create(['events' => ['issue.created']]);

    $this->actingAs($admin)->patch("/projects/THI/webhooks/{$webhook->id}", ['active' => false]);

    expect($webhook->fresh()->subscribedEvents())->toBe(['issue.created'])
        ->and($webhook->fresh()->active)->toBeFalse();
});

it('offers the available events on the settings page', function () {
    $admin = member($this->project);

    $this->actingAs($admin)
        ->get('/projects/THI/webhooks')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('availableEvents', 6));
});
