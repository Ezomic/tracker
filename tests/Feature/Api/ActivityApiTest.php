<?php

declare(strict_types=1);

use App\Actions\CreateServiceAccountAction;
use App\Enums\IssuePriority;
use App\Enums\OrganizationRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
    // IssueObserver records a `created` entry the moment the factory saves, so
    // every issue starts with exactly one activity. Tests count from there.
    $this->issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);
});

it('returns the activity behind an issue', function () {
    $this->issue->recordActivity('status_changed', ['from' => 'backlog', 'to' => 'done']);

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues/THI-1/activity');

    $response->assertOk();
    expect($response->json('data.*.type'))->toBe(['created', 'status_changed'])
        ->and($response->json('data.1.data'))->toBe(['from' => 'backlog', 'to' => 'done'])
        ->and($response->json('meta.total'))->toBe(2);
});

it('reads oldest first, matching how the timeline reads', function () {
    $this->travel(1)->minutes();
    $this->issue->recordActivity('renamed');

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues/THI-1/activity');

    expect($response->json('data.*.type'))->toBe(['created', 'renamed']);
});

it('attributes an entry to the actor', function () {
    $this->actingAs($this->user);
    $this->issue->recordActivity('renamed');

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues/THI-1/activity?type=renamed');

    expect($response->json('data.0.user'))->toBe($this->user->email);
});

it('leaves the actor null when nothing was signed in', function () {
    $this->issue->recordActivity('renamed');

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues/THI-1/activity?type=renamed');

    expect($response->json('data.0.user'))->toBeNull();
});

it('records what a real change did, end to end', function () {
    $this->actingAs($this->user, 'sanctum')
        ->patchJson('/api/issues/THI-1', ['priority' => IssuePriority::High->value])
        ->assertOk();

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues/THI-1/activity');

    $types = $response->json('data.*.type');
    expect($types)->toContain('priority_changed');
});

it('filters by type', function () {
    $this->issue->recordActivity('status_changed', ['to' => 'done']);
    $this->issue->recordActivity('renamed');

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues/THI-1/activity?type=status_changed');

    expect($response->json('data.*.type'))->toBe(['status_changed'])
        ->and($response->json('meta.total'))->toBe(1);
});

it('returns an empty page for a type that does not exist rather than a 422', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues/THI-1/activity?type=invented_later');

    $response->assertOk();
    expect($response->json('data'))->toBe([])
        ->and($response->json('meta.total'))->toBe(0);
});

it('paginates', function () {
    // Four here plus the automatic `created` makes five.
    foreach (range(1, 4) as $i) {
        $this->issue->recordActivity('renamed', ['n' => $i]);
    }

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues/THI-1/activity?per_page=2');

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta'))->toMatchArray([
            'total' => 5,
            'per_page' => 2,
            'current_page' => 1,
            'last_page' => 3,
        ]);
});

it('rejects a per_page above the ceiling', function () {
    $this->actingAs($this->user, 'sanctum')->getJson('/api/issues/THI-1/activity?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('is closed to someone who cannot see the issue', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger, 'sanctum')->getJson('/api/issues/THI-1/activity')->assertForbidden();
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/issues/THI-1/activity')->assertUnauthorized();
});

it('lets a service account reconcile its own filings', function () {
    [$organization, $owner] = organizationWith(OrganizationRole::Owner);
    $project = projectInOrganization($organization, $owner, ['key' => 'ACME']);
    (new CreateServiceAccountAction)->handle($organization, 'snag ingest', [$project]);
    $account = User::query()->where('is_service', true)->firstOrFail();
    $issue = Issue::factory()->for($project)->create(['identifier' => 'ACME-1']);
    $issue->recordActivity('status_changed', ['to' => 'done']);

    Sanctum::actingAs($account, CreateServiceAccountAction::ABILITIES);

    $this->getJson('/api/issues/ACME-1/activity?type=status_changed')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'status_changed');
});

it('refuses a service token without the issues read ability', function () {
    [$organization, $owner] = organizationWith(OrganizationRole::Owner);
    $project = projectInOrganization($organization, $owner, ['key' => 'ACME']);
    (new CreateServiceAccountAction)->handle($organization, 'snag ingest', [$project]);
    $account = User::query()->where('is_service', true)->firstOrFail();
    Issue::factory()->for($project)->create(['identifier' => 'ACME-1']);

    Sanctum::actingAs($account, ['issues:create']);

    $this->getJson('/api/issues/ACME-1/activity')->assertForbidden();
});
