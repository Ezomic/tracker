<?php

declare(strict_types=1);

use App\Actions\CreateServiceAccountAction;
use App\Enums\OrganizationRole;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->live = Project::factory()->create(['key' => 'LIVE']);
    $this->gone = Project::factory()->create(['key' => 'GONE', 'archived_at' => now()]);
    joinProjects($this->user, [$this->live, $this->gone]);
});

it('excludes archived projects by default, as it always has', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects');

    $response->assertOk();
    expect($response->json('*.key'))->toBe(['LIVE']);
});

it('includes archived projects on request', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects?archived=include');

    expect($response->json('*.key'))->toBe(['GONE', 'LIVE']);
});

it('lists only archived projects on request', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects?archived=only');

    expect($response->json('*.key'))->toBe(['GONE']);
});

it('reports archived_at so absence is no longer indistinguishable from never existing', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects?archived=include');

    $rows = collect($response->json())->keyBy('key');
    expect($rows['GONE']['archived_at'])->not->toBeNull()
        ->and($rows['LIVE']['archived_at'])->toBeNull();
});

it('reports the category so a consumer can tell project kinds apart', function () {
    $category = Category::factory()->create();
    $this->live->forceFill(['category_id' => $category->id])->save();

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects');

    expect($response->json('0.category_id'))->toBe($category->id);
});

it('rejects an unknown archived value', function () {
    $this->actingAs($this->user, 'sanctum')->getJson('/api/projects?archived=maybe')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('archived');
});

it('still scopes to what the caller can see', function () {
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger, 'sanctum')->getJson('/api/projects?archived=include');

    expect($response->json())->toBe([]);
});

it('lets a service account list projects', function () {
    [$organization, $owner] = organizationWith(OrganizationRole::Owner);
    $project = projectInOrganization($organization, $owner, ['key' => 'THI']);
    (new CreateServiceAccountAction)->handle($organization, 'snag ingest', [$project]);
    $account = User::query()->where('is_service', true)->firstOrFail();

    Sanctum::actingAs($account, CreateServiceAccountAction::ABILITIES);

    $response = $this->getJson('/api/projects');

    $response->assertOk();
    expect($response->json('*.key'))->toBe(['THI']);
});

it('refuses a service token without the projects ability', function () {
    [$organization, $owner] = organizationWith(OrganizationRole::Owner);
    $project = projectInOrganization($organization, $owner, ['key' => 'THI']);
    (new CreateServiceAccountAction)->handle($organization, 'snag ingest', [$project]);
    $account = User::query()->where('is_service', true)->firstOrFail();

    Sanctum::actingAs($account, ['issues:read']);

    $this->getJson('/api/projects')->assertForbidden();
});

it('keeps the rest of the project routes closed to a service account', function () {
    [$organization, $owner] = organizationWith(OrganizationRole::Owner);
    $project = projectInOrganization($organization, $owner, ['key' => 'THI']);
    (new CreateServiceAccountAction)->handle($organization, 'snag ingest', [$project]);
    $account = User::query()->where('is_service', true)->firstOrFail();

    Sanctum::actingAs($account, CreateServiceAccountAction::ABILITIES);

    $this->getJson('/api/projects/THI/members')->assertForbidden();
    $this->postJson('/api/projects', ['key' => 'NEW', 'name' => 'New'])->assertForbidden();
    $this->deleteJson('/api/projects/THI')->assertForbidden();
});

it('mints service tokens carrying the projects ability', function () {
    [$organization, $owner] = organizationWith(OrganizationRole::Owner);
    $project = projectInOrganization($organization, $owner, ['key' => 'THI']);

    $token = (new CreateServiceAccountAction)->handle($organization, 'snag ingest', [$project]);

    expect($token->accessToken->abilities)->toContain('projects:read');
});
