<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

it('returns the full detail of an issue by identifier', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    $issue = Issue::factory()->for($thi)->create([
        'number' => 168,
        'identifier' => 'THI-168',
        'title' => 'Fretboard quiz per-string accuracy breakdown',
        'description' => 'Track and surface which strings the learner misses.',
        'type' => IssueType::Fix,
        'priority' => IssuePriority::Medium,
        'status' => IssueStatus::Done,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/issues/THI-168');

    $response->assertOk()->assertJson([
        'identifier' => 'THI-168',
        'number' => 168,
        'title' => 'Fretboard quiz per-string accuracy breakdown',
        'description' => 'Track and surface which strings the learner misses.',
        'type' => 'fix',
        'priority' => 'medium',
        'status' => 'done',
        'project' => 'THI',
        'parent' => null,
        'url' => url('/issues/THI-168'),
        'branch_name' => $issue->branch_name,
    ]);
});

it('includes the parent identifier when the issue sits under an epic', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    $epic = Issue::factory()->for($thi)->create(['identifier' => 'THI-1']);
    Issue::factory()->for($thi)->create(['identifier' => 'THI-2', 'parent_id' => $epic->id]);

    $this->actingAs($user, 'sanctum')->getJson('/api/issues/THI-2')
        ->assertOk()
        ->assertJsonPath('parent', 'THI-1');
});

it('returns 404 for an unknown identifier', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/issues/THI-999')->assertNotFound();
});

it('rejects unauthenticated requests', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    Issue::factory()->for($thi)->create(['identifier' => 'THI-1']);

    $this->getJson('/api/issues/THI-1')->assertUnauthorized();
});
