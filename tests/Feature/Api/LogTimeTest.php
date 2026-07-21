<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

it('logs time against an issue via the API', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    $issue = Issue::factory()->for($project)->create(['identifier' => 'THI-42']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/issues/THI-42/time', [
            'duration' => '1h 30m',
            'spent_on' => '2026-07-21',
            'note' => 'Level 9 fixes',
        ])
        ->assertCreated()
        ->assertJson([
            'issue' => 'THI-42',
            'minutes' => 90,
            'spentOn' => '2026-07-21',
            'note' => 'Level 9 fixes',
        ]);

    expect($issue->timeEntries()->sum('minutes'))->toBe(90);
});

it('defaults spent_on to today when omitted', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    Issue::factory()->for($project)->create(['identifier' => 'THI-7']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/issues/THI-7/time', ['duration' => '45m'])
        ->assertCreated()
        ->assertJson(['minutes' => 45, 'spentOn' => now()->toDateString()]);
});

it('rejects an invalid duration', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    Issue::factory()->for($project)->create(['identifier' => 'THI-8']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/issues/THI-8/time', ['duration' => 'not-a-duration'])
        ->assertUnprocessable();
});

it('requires authentication', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    Issue::factory()->for($project)->create(['identifier' => 'THI-9']);

    $this->postJson('/api/issues/THI-9/time', ['duration' => '1h'])->assertUnauthorized();
});

it('forbids logging time on an issue the user cannot access', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    Issue::factory()->for($project)->create(['identifier' => 'THI-10']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/issues/THI-10/time', ['duration' => '1h'])
        ->assertForbidden();
});
