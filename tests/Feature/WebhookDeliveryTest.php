<?php

declare(strict_types=1);

use App\Actions\DeliverWebhookAction;
use App\Enums\IssueStatus;
use App\Jobs\DeliverWebhookJob;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectWebhook;
use App\Services\Webhooks\WebhookTargetRefused;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
    resolveHosts(['snag.test' => ['93.184.216.34'], 'flare.test' => ['93.184.216.35']]);
});

it('queues a delivery to every active endpoint when an issue changes status', function () {
    Queue::fake();
    $webhook = ProjectWebhook::factory()->for($this->project)->create();
    ProjectWebhook::factory()->for($this->project)->create(['url' => 'https://flare.test/hook']);
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $issue->forceFill(['status' => IssueStatus::Done])->save();

    Queue::assertPushed(DeliverWebhookJob::class, 2);
    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->webhook->is($webhook)
        && $job->event === 'issue.status_changed'
        && $job->payload['issue']['identifier'] === 'THI-1');
});

it('skips inactive endpoints', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->inactive()->create();
    $issue = Issue::factory()->for($this->project)->create();

    $issue->forceFill(['status' => IssueStatus::Done])->save();

    Queue::assertNothingPushed();
});

it('does not notify another project endpoints', function () {
    Queue::fake();
    $other = Project::factory()->create(['key' => 'BILLR']);
    ProjectWebhook::factory()->for($other)->create();
    $issue = Issue::factory()->for($this->project)->create();

    $issue->forceFill(['status' => IssueStatus::Done])->save();

    Queue::assertNothingPushed();
});

it('does not fire on a change that is not the status', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create();
    $issue = Issue::factory()->for($this->project)->create();

    $issue->forceFill(['title' => 'Renamed'])->save();

    Queue::assertNothingPushed();
});

it('carries source and external_ref so a consumer can match its own record', function () {
    Queue::fake();
    ProjectWebhook::factory()->for($this->project)->create();
    $issue = Issue::factory()->for($this->project)->create([
        'source' => 'snag',
        'external_ref' => '42',
        'external_reporter' => 'pseudonym:a1b2c3',
    ]);

    $issue->forceFill(['status' => IssueStatus::Done])->save();

    Queue::assertPushed(DeliverWebhookJob::class, fn (DeliverWebhookJob $job): bool => $job->payload['issue']['source'] === 'snag'
        && $job->payload['issue']['external_ref'] === '42'
        && $job->payload['issue']['external_reporter'] === 'pseudonym:a1b2c3'
        && $job->payload['project'] === 'THI');
});

it('signs the body with the endpoint secret', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $webhook = ProjectWebhook::factory()->for($this->project)->create(['secret' => 'shhh']);

    app(DeliverWebhookAction::class)->handle($webhook, 'ping', ['event' => 'ping']);

    Http::assertSent(function ($request) {
        $expected = 'sha256='.hash_hmac('sha256', $request->body(), 'shhh');

        return $request->header(DeliverWebhookAction::SIGNATURE_HEADER)[0] === $expected
            && $request->header(DeliverWebhookAction::EVENT_HEADER)[0] === 'ping';
    });
});

it('records the outcome of a successful delivery', function () {
    Http::fake(['*' => Http::response('', 204)]);
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    app(DeliverWebhookAction::class)->handle($webhook, 'ping', []);

    expect($webhook->fresh()->last_status)->toBe(204)
        ->and($webhook->fresh()->last_error)->toBeNull()
        ->and($webhook->fresh()->last_delivered_at)->not->toBeNull();
});

it('records the failure and throws so the queue retries', function () {
    Http::fake(['*' => Http::response('endpoint exploded', 500)]);
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    expect(fn () => app(DeliverWebhookAction::class)->handle($webhook, 'ping', []))
        ->toThrow(RuntimeException::class);

    expect($webhook->fresh()->last_status)->toBe(500)
        ->and($webhook->fresh()->last_error)->toBe('endpoint exploded');
});

it('refuses to deliver to a host that now resolves to a private address', function () {
    Http::fake();
    resolveHosts(['snag.test' => ['127.0.0.1']]);
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    expect(fn () => app(DeliverWebhookAction::class)->handle($webhook, 'ping', []))
        ->toThrow(WebhookTargetRefused::class);

    Http::assertNothingSent();
    expect($webhook->fresh()->last_status)->toBeNull()
        ->and($webhook->fresh()->last_error)->toBe('The webhook host points at a private or reserved address.');
});

it('does not follow a redirect to another address', function () {
    Http::fake([
        'snag.test/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1:5184/internal']),
        '*' => Http::response('internal secrets', 200),
    ]);
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    expect(fn () => app(DeliverWebhookAction::class)->handle($webhook, 'ping', []))
        ->toThrow(RuntimeException::class);

    Http::assertSentCount(1);
    expect($webhook->fresh()->last_status)->toBe(302);
});

it('gives up after four attempts rather than retrying forever', function () {
    $job = new DeliverWebhookJob(ProjectWebhook::factory()->for($this->project)->create(), 'ping', []);

    expect($job->tries)->toBe(4)
        ->and($job->backoff())->toBe([10, 60, 300]);
});
