<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
            ->where('attention', [])
            ->where('board.backlog', [])
            ->has('trend', 8)
        );
});

it('renders stats and status breakdown', function () {
    $project = seedDashboard();

    $this->actingAs(member($project))
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('stats.open', 6) // 3 backlog + 2 in progress + 1 in review (archived excluded)
            ->where('stats.in_progress', 2)
            ->where('stats.in_review', 1)
            ->where('stats.done', 4)
            ->where('stats.archived', 1)
            ->where('statusBreakdown.backlog', 3)
            ->where('statusBreakdown.done', 4)
        );
});

it('counts active tickets per project excluding done and archived', function () {
    $project = seedDashboard();

    $this->actingAs(member($project))
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('activeByProject.0.key', 'THI')
            ->where('activeByProject.0.count', 6)
        );
});

it('surfaces the users own open issues, stalest first, flagging stale ones', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    $stale = Issue::factory()->for($project)->create([
        'status' => IssueStatus::Backlog,
        'assignee_id' => $user->id,
    ]);
    $stale->forceFill(['updated_at' => now()->subDays(20)])->save();

    Issue::factory()->for($project)->create([
        'status' => IssueStatus::InProgress,
        'owner_id' => $user->id,
    ]);

    // Not owned by or assigned to the user: must not appear.
    Issue::factory()->for($project)->create(['status' => IssueStatus::Backlog]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('attention', 2)
            ->where('attention.0.identifier', $stale->identifier)
            ->where('attention.0.stale', true)
            ->where('attention.1.stale', false)
        );
});

it('groups board columns by status', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $review = Issue::factory()->for($project)->create(['status' => IssueStatus::InReview]);
    $done = Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now()->subDay(),
    ]);

    $this->actingAs(member($project))
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('board.in_review.0.identifier', $review->identifier)
            ->where('board.done.0.identifier', $done->identifier)
            ->where('board.backlog', [])
        );
});

it('reports weekly metrics and a work-in-progress load', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now(),
        'created_at' => now()->subDays(3),
    ]);
    Issue::factory()->for($project)->count(2)->create(['status' => IssueStatus::InProgress]);
    Issue::factory()->for($project)->create(['status' => IssueStatus::InReview]);

    $this->actingAs(member($project))
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('trend', 8)
            ->where('metrics.completed', 1)
            ->where('metrics.wip', 3)
        );
});

it('computes the weekly trend only once per dashboard load', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    DB::enableQueryLog();
    $this->actingAs($user)->get('/dashboard')->assertOk();

    // The completed-trend query selects exactly created_at + closed_at; before
    // the fix metrics() re-ran trend(), so it appeared twice.
    $trendQueries = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'select "created_at", "closed_at" from "issues"'));

    expect($trendQueries)->toHaveCount(1);

    DB::disableQueryLog();
});
