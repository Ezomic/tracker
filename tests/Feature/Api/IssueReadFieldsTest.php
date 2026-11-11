<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Label;

it('returns labels from the detail endpoint', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI', 'next_number' => 0]);
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'backend']);
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'api']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Round-trip me',
        'type' => 'feature',
        'labels' => ['backend', 'api'],
    ])->assertCreated();

    $identifier = Issue::query()->where('title', 'Round-trip me')->firstOrFail()->identifier;

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/issues/{$identifier}")->assertOk();

    expect($response->json('labels'))->toEqualCanonicalizing(['backend', 'api']);
});

it('returns an empty label list when unset', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI', 'next_number' => 0]);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Bare issue',
        'type' => 'feature',
    ])->assertCreated();

    $identifier = Issue::query()->where('title', 'Bare issue')->firstOrFail()->identifier;

    $this->actingAs($user, 'sanctum')->getJson("/api/issues/{$identifier}")
        ->assertOk()
        ->assertJson(['labels' => []]);
});

it('includes labels in the list endpoint', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI', 'next_number' => 0]);
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'infra']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Listed',
        'type' => 'feature',
        'labels' => ['infra'],
    ])->assertCreated();

    $this->actingAs($user, 'sanctum')->getJson('/api/issues?project=THI')
        ->assertOk()
        ->assertJsonFragment(['labels' => ['infra']]);
});
