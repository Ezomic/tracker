<?php

declare(strict_types=1);

use App\Actions\CreateServiceAccountAction;
use App\Enums\OrganizationRole;
use App\Mail\LoginCodeMail;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    [$this->organization, $this->owner] = organizationWith(OrganizationRole::Owner);
    $this->granted = projectInOrganization($this->organization, $this->owner, ['key' => 'THI']);
    $this->ungranted = projectInOrganization($this->organization, $this->owner, ['key' => 'BILLR']);

    $this->token = (new CreateServiceAccountAction)->handle(
        $this->organization,
        'snag ingest',
        [$this->granted],
    );

    $this->account = User::query()->where('is_service', true)->firstOrFail();
});

function actAsService(User $account): void
{
    Sanctum::actingAs($account, CreateServiceAccountAction::ABILITIES);
}

it('can file an issue into a granted project', function () {
    actAsService($this->account);

    $this->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Total is wrong on the invoice screen',
        'type' => 'fix',
        'source' => 'snag',
        'external_ref' => '99',
        'external_reporter' => 'pseudonym:a1b2c3',
    ])->assertCreated();

    expect(Issue::query()->firstOrFail()->external_reporter)->toBe('pseudonym:a1b2c3');
});

it('cannot file into a project it was not granted', function () {
    actAsService($this->account);

    $this->postJson('/api/issues', [
        'project' => 'BILLR',
        'title' => 'Not mine to file',
        'type' => 'fix',
    ])->assertForbidden();

    expect(Issue::query()->count())->toBe(0);
});

it('reads only the issues of its granted projects', function () {
    Issue::factory()->for($this->granted)->create(['identifier' => 'THI-1']);
    Issue::factory()->for($this->ungranted)->create(['identifier' => 'BILLR-1']);

    actAsService($this->account);

    expect($this->getJson('/api/issues')->json('data.*.identifier'))->toBe(['THI-1']);
});

it('cannot reach the rest of the api', function () {
    actAsService($this->account);

    // /api/projects is deliberately readable (TRACK-220): a service account has
    // to know which project keys exist before it can file into one.
    $this->getJson('/api/projects')->assertOk();
    $this->getJson('/api/labels')->assertForbidden();
    $this->getJson('/api/members?project=THI')->assertForbidden();
    $this->postJson('/api/projects', ['key' => 'NEW', 'name' => 'New'])->assertForbidden();
    $this->postJson('/api/labels', ['name' => 'sneaky'])->assertForbidden();
});

it('cannot change or archive an issue it filed', function () {
    $issue = Issue::factory()->for($this->granted)->create(['identifier' => 'THI-1']);

    actAsService($this->account);

    $this->patchJson("/api/issues/{$issue->identifier}", ['title' => 'Rewritten'])->assertForbidden();
    $this->patchJson("/api/issues/{$issue->identifier}/status", ['status' => 'done'])->assertForbidden();
    $this->deleteJson("/api/issues/{$issue->identifier}")->assertForbidden();

    expect($issue->fresh()->title)->not->toBe('Rewritten')
        ->and($issue->fresh()->archived_at)->toBeNull();
});

it('mints a token limited to filing issues and reading issues and projects', function () {
    expect($this->token->accessToken->abilities)->toBe(['issues:create', 'issues:read', 'projects:read']);
});

it('refuses a service token whose abilities were narrowed further', function () {
    Sanctum::actingAs($this->account, ['issues:read']);

    $this->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Read-only token should not file',
        'type' => 'fix',
    ])->assertForbidden();

    $this->getJson('/api/issues')->assertOk();
});

it('still lets a session-authenticated user reach the issue routes', function () {
    // Sanctum's own abilities middleware rejects session auth, which has no
    // access token at all. RequireServiceAbility must not.
    $this->actingAs($this->owner, 'sanctum')->getJson('/api/issues')->assertOk();
    $this->actingAs($this->owner, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Filed over a session',
        'type' => 'feature',
    ])->assertCreated();
});

it('leaves a personal token able to do everything it could before', function () {
    Sanctum::actingAs($this->owner, ['*']);

    $this->getJson('/api/projects')->assertOk();
    $this->getJson('/api/issues')->assertOk();
    $this->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Filed by a human',
        'type' => 'feature',
    ])->assertCreated();
});

it('never lets a service account hold a session', function () {
    $this->actingAs($this->account)->get('/dashboard')->assertForbidden();

    expect(auth()->check())->toBeFalse();
});

it('does not email a login code to a service account address', function () {
    Mail::fake();

    $this->post('/login/code', ['email' => $this->account->email]);

    Mail::assertNothingQueued();
});

it('still emails a login code to a real user', function () {
    Mail::fake();

    $this->post('/login/code', ['email' => $this->owner->email]);

    Mail::assertQueued(LoginCodeMail::class);
});
