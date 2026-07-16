<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

it('lists issues ordered by project then number', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    Issue::factory()->for($thi)->create(['number' => 2, 'identifier' => 'THI-2', 'title' => 'Second']);
    Issue::factory()->for($thi)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'First']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues');

    $response->assertOk();
    expect($response->json('*.identifier'))->toBe(['THI-1', 'THI-2']);
    $response->assertJsonFragment([
        'identifier' => 'THI-1',
        'title' => 'First',
        'project' => 'THI',
    ]);
});

it('filters issues by project key', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    $billr = Project::factory()->create(['key' => 'BILLR']);
    joinProjects($user, [$thi, $billr]);
    Issue::factory()->for($thi)->create(['identifier' => 'THI-1']);
    Issue::factory()->for($billr)->create(['identifier' => 'BILLR-1']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues?project=THI');

    $response->assertOk();
    expect($response->json('*.identifier'))->toBe(['THI-1']);
});

it('excludes archived issues', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $thi);
    Issue::factory()->for($thi)->create(['identifier' => 'THI-1']);
    Issue::factory()->for($thi)->create(['identifier' => 'THI-2', 'archived_at' => now()]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues');

    expect($response->json('*.identifier'))->toBe(['THI-1']);
});

it('returns 422 for an unknown project filter', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/issues?project=NOPE')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('project');
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/issues')->assertUnauthorized();
});
