<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

function seedDashboard(): Project
{
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software']);

    Issue::factory()->for($project)->count(3)->create(['status' => IssueStatus::Backlog]);
    Issue::factory()->for($project)->count(2)->create(['status' => IssueStatus::InProgress]);
    Issue::factory()->for($project)->create(['status' => IssueStatus::InReview]);
    Issue::factory()->for($project)->count(4)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now()->subDay(),
    ]);
    Issue::factory()->for($project)->create([
        'status' => IssueStatus::Backlog,
        'archived_at' => now(),
    ]);

    return $project;
}

it('redirects guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('lets authenticated users visit an empty dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.open', 0)
            ->where('activeByProject', [])
        );
});

it('renders stats and status breakdown', function () {
    seedDashboard();

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('stats.open', 6) // 3 backlog + 2 in progress + 1 in review (archived excluded)
            ->where('stats.in_progress', 2)
            ->where('stats.in_review', 1)
            ->where('stats.done', 4)
            ->where('statusBreakdown.backlog', 3)
            ->where('statusBreakdown.done', 4)
        );
});

it('counts active tickets per project excluding done and archived', function () {
    seedDashboard();

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('activeByProject.0.key', 'THI')
            ->where('activeByProject.0.count', 6)
        );
});

it('lists in-review tickets and this-week completions', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $review = Issue::factory()->for($project)->create(['status' => IssueStatus::InReview]);
    $done = Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now()->subDays(2),
    ]);
    Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now()->subDays(30),
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('inReview.0.identifier', $review->identifier)
            ->has('recentlyCompleted', 1)
            ->where('recentlyCompleted.0.identifier', $done->identifier)
        );
});

it('orders stale tickets by oldest update among open issues', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    Issue::factory()->for($project)->create(['status' => IssueStatus::Backlog]);
    $stale = Issue::factory()->for($project)->create(['status' => IssueStatus::Backlog]);
    $stale->forceFill(['updated_at' => now()->subDays(90)])->save();
    Issue::factory()->for($project)->create(['status' => IssueStatus::Done]);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('stale.0.identifier', $stale->identifier)
        );
});
