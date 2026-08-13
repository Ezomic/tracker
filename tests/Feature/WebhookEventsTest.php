<?php

declare(strict_types=1);

use App\Actions\AddCommentAction;
use App\Actions\NotifyIssueWebhooksAction;
use App\Enums\IssueStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhookJob;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectWebhook;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    NotifyIssueWebhooksAction::reset();
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

it('caps deliveries in one request rather than flooding the queue', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::Created->value],
    ]);

    foreach (range(1, NotifyIssueWebhooksAction::PER_REQUEST_CAP + 10) as $n) {
        Issue::factory()->for($this->project)->create(['number' => $n, 'identifier' => "THI-{$n}"]);
    }

    Queue::assertPushed(DeliverWebhookJob::class, NotifyIssueWebhooksAction::PER_REQUEST_CAP);
});

it('tells the endpoint how many it did not hear about', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create([
        'events' => [WebhookEvent::Created->value],
    ]);

    foreach (range(1, NotifyIssueWebhooksAction::PER_REQUEST_CAP + 3) as $n) {
        Issue::factory()->for($this->project)->create(['number' => $n, 'identifier' => "THI-{$n}"]);
    }

    app(NotifyIssueWebhooksAction::class)->flushSuppressed($this->project);

    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === 'issue.bulk_changed'
        && $job->payload['suppressed'] === 3);
});

it('sends no summary when nothing was suppressed', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create();

    app(NotifyIssueWebhooksAction::class)->flushSuppressed($this->project);

    Queue::assertNotPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->event === 'issue.bulk_changed');
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
