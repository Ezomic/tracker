<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

it('shares open and total ticket counts per project', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    Issue::factory()->for($project)->count(2)->create(['status' => IssueStatus::Backlog]);
    Issue::factory()->for($project)->create(['status' => IssueStatus::InProgress]);
    Issue::factory()->for($project)->create(['status' => IssueStatus::Done]);
    Issue::factory()->for($project)->create([
        'status' => IssueStatus::Backlog,
        'archived_at' => now(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.key', 'THI')
            ->where('projects.0.openCount', 3)
            ->where('projects.0.totalCount', 4)
        );
});

it('reports zero counts for a project with no tickets', function () {
    Project::factory()->create(['key' => 'THI']);

    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.openCount', 0)
            ->where('projects.0.totalCount', 0)
        );
});
