<?php

declare(strict_types=1);

use App\Actions\DeliverWebhookAction;
use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\ProjectWebhook;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->admin = member($this->project);
});

it('adds a webhook and shows the signing secret once', function () {
    $response = $this->actingAs($this->admin)->post('/projects/THI/webhooks', [
        'url' => 'https://snag.test/webhooks/tracker',
    ]);

    $response->assertRedirect('/projects/THI/webhooks');
    $response->assertSessionHas('createdSecret');

    $webhook = ProjectWebhook::query()->firstOrFail();
    expect($webhook->url)->toBe('https://snag.test/webhooks/tracker')
        ->and($webhook->active)->toBeTrue()
        ->and($webhook->secret)->toBe(session('createdSecret'));
});

it('never serializes the secret onto the page', function () {
    ProjectWebhook::factory()->for($this->project)->create(['secret' => 'top-secret-value']);

    $response = $this->actingAs($this->admin)->get('/projects/THI/webhooks');

    $response->assertOk();
    expect($response->getContent())->not->toContain('top-secret-value');
});

it('refuses a plain http endpoint', function () {
    $this->actingAs($this->admin)
        ->post('/projects/THI/webhooks', ['url' => 'http://snag.test/hook'])
        ->assertSessionHasErrors('url');

    expect(ProjectWebhook::query()->count())->toBe(0);
});

it('refuses a malformed url', function () {
    $this->actingAs($this->admin)
        ->post('/projects/THI/webhooks', ['url' => 'not a url'])
        ->assertSessionHasErrors('url');
});

it('sends a ping and reports success', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    $this->actingAs($this->admin)
        ->post("/projects/THI/webhooks/{$webhook->id}/test")
        ->assertRedirect('/projects/THI/webhooks');

    Http::assertSent(fn ($request) => $request->header(DeliverWebhookAction::EVENT_HEADER)[0] === 'ping');
    expect($webhook->fresh()->last_status)->toBe(200);
});

it('reports a failing ping without throwing', function () {
    Http::fake(['*' => Http::response('nope', 502)]);
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    $this->actingAs($this->admin)
        ->post("/projects/THI/webhooks/{$webhook->id}/test")
        ->assertRedirect('/projects/THI/webhooks');

    expect($webhook->fresh()->last_status)->toBe(502)
        ->and($webhook->fresh()->last_error)->toBe('nope');
});

it('toggles a webhook off and on', function () {
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    $this->actingAs($this->admin)->patch("/projects/THI/webhooks/{$webhook->id}", ['active' => false]);
    expect($webhook->fresh()->active)->toBeFalse();

    $this->actingAs($this->admin)->patch("/projects/THI/webhooks/{$webhook->id}", ['active' => true]);
    expect($webhook->fresh()->active)->toBeTrue();
});

it('removes a webhook', function () {
    $webhook = ProjectWebhook::factory()->for($this->project)->create();

    $this->actingAs($this->admin)
        ->delete("/projects/THI/webhooks/{$webhook->id}")
        ->assertRedirect('/projects/THI/webhooks');

    expect(ProjectWebhook::query()->count())->toBe(0);
});

it('is closed to members who cannot administer the project', function () {
    $writer = member($this->project, ProjectLevel::Write);

    $this->actingAs($writer)->get('/projects/THI/webhooks')->assertForbidden();
    $this->actingAs($writer)
        ->post('/projects/THI/webhooks', ['url' => 'https://snag.test/hook'])
        ->assertForbidden();
});

it('cannot touch a webhook belonging to another project', function () {
    $other = Project::factory()->create(['key' => 'BILLR']);
    $theirs = ProjectWebhook::factory()->for($other)->create();

    $this->actingAs($this->admin)->delete("/projects/THI/webhooks/{$theirs->id}")->assertNotFound();
    $this->actingAs($this->admin)->post("/projects/THI/webhooks/{$theirs->id}/test")->assertNotFound();
    $this->actingAs($this->admin)->patch("/projects/THI/webhooks/{$theirs->id}", ['active' => false])->assertNotFound();

    expect($theirs->fresh())->not->toBeNull()
        ->and($theirs->fresh()->active)->toBeTrue();
});

it('drops its webhooks when the project is deleted', function () {
    ProjectWebhook::factory()->for($this->project)->create();

    $this->project->delete();

    expect(ProjectWebhook::query()->count())->toBe(0);
});
