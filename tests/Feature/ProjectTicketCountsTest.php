<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;

it('shares a per-status ticket breakdown per project', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    Issue::factory()->for($project)->count(2)->create(['status' => IssueStatus::Backlog]);
    Issue::factory()->for($project)->create(['status' => IssueStatus::InProgress]);
    Issue::factory()->for($project)->create(['status' => IssueStatus::InReview]);
    Issue::factory()->for($project)->count(3)->create(['status' => IssueStatus::Done]);
    Issue::factory()->for($project)->create([
        'status' => IssueStatus::Backlog,
        'archived_at' => now(),
    ]);

    $this->actingAs(member($project))
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->where('sidebarProjects.0.key', 'THI')
            ->where('sidebarProjects.0.counts.backlog', 2)
            ->where('sidebarProjects.0.counts.in_progress', 1)
            ->where('sidebarProjects.0.counts.in_review', 1)
            ->where('sidebarProjects.0.counts.done', 3)
        );
});

it('reports zero counts for a project with no tickets', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    $this->actingAs(member($project))
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->where('sidebarProjects.0.counts.backlog', 0)
            ->where('sidebarProjects.0.counts.in_progress', 0)
            ->where('sidebarProjects.0.counts.in_review', 0)
            ->where('sidebarProjects.0.counts.done', 0)
        );
});

it('still shares the sidebar breakdown on the projects page', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    Issue::factory()->for($project)->create(['status' => IssueStatus::InProgress]);

    $this->actingAs(member($project))
        ->get('/projects')
        ->assertInertia(fn ($page) => $page
            ->component('projects/Index')
            ->where('sidebarProjects.0.key', 'THI')
            ->where('sidebarProjects.0.counts.in_progress', 1)
        );
});
