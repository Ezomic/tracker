<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\TimeEntry;
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
            ->where('completedByWeek.series', [])
            ->where('completedByWeek.grandTotal', 0)
            ->has('completedByWeek.weeks', 8)
            ->where('metrics.completed', 0)
            ->where('time.loggedThisWeek', 0)
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
            ->where('stats.urgentOpen', 0)
            ->where('statusBreakdown.backlog', 3)
            ->where('statusBreakdown.done', 4)
        );
});

it('counts open urgent issues, excluding done and archived ones', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    Issue::factory()->for($project)->count(2)->create([
        'status' => IssueStatus::InProgress,
        'priority' => IssuePriority::Urgent,
    ]);
    Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'priority' => IssuePriority::Urgent,
        'closed_at' => now(),
    ]);
    Issue::factory()->for($project)->create([
        'status' => IssueStatus::Backlog,
        'priority' => IssuePriority::Urgent,
        'archived_at' => now(),
    ]);

    $this->actingAs(member($project))
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('stats.urgentOpen', 2));
});

it('counts active tickets per project excluding done and archived', function () {
    $project = seedDashboard();

    $this->actingAs(member($project))
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('activeByProject.0.key', 'THI')
            ->where('activeByProject.0.count', 6)
            ->where('activeByProject.0.other', false)
        );
});

it('caps active-by-project at the top six and folds the rest into Other', function () {
    $projects = collect(range(1, 8))->map(fn (int $n): Project => Project::factory()->create(['key' => "P{$n}"]));
    $user = member($projects->all());

    // Give each project a distinct active count: P1 => 8 ... P8 => 1.
    $projects->each(fn (Project $project, int $index) => Issue::factory()
        ->for($project)
        ->count(8 - $index)
        ->create(['status' => IssueStatus::InProgress]));

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('activeByProject', 7)
            ->where('activeByProject.0.count', 8)
            ->where('activeByProject.6.other', true)
            ->where('activeByProject.6.name', 'Other (2)')
            ->where('activeByProject.6.count', 3) // the two smallest: 2 + 1
        );
});

it('orders the attention list by priority first, then staleness', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    $stale = Issue::factory()->for($project)->create([
        'status' => IssueStatus::Backlog,
        'priority' => IssuePriority::Low,
        'assignee_id' => $user->id,
    ]);
    $stale->forceFill(['updated_at' => now()->subDays(20)])->save();

    $urgent = Issue::factory()->for($project)->create([
        'status' => IssueStatus::InProgress,
        'priority' => IssuePriority::Urgent,
        'owner_id' => $user->id,
    ]);

    // Not owned by or assigned to the user: must not appear.
    Issue::factory()->for($project)->create(['status' => IssueStatus::Backlog]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('attention', 2)
            ->where('attention.0.identifier', $urgent->identifier)
            ->where('attention.0.priority', 'urgent')
            ->where('attention.1.identifier', $stale->identifier)
            ->where('attention.1.stale', true)
        );
});

it('buckets completed issues into weekly per-project series, including archived ones', function () {
    $a = Project::factory()->create(['key' => 'AAA']);
    $b = Project::factory()->create(['key' => 'BBB']);
    $user = member([$a, $b]);

    Issue::factory()->for($a)->count(2)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now(),
    ]);
    Issue::factory()->for($b)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now()->subWeek(),
    ]);
    // Archived done issue: still counts in its completion week (completion is historical).
    Issue::factory()->for($a)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now(),
        'archived_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('completedByWeek.series.0.key', 'AAA')
            ->where('completedByWeek.series.0.total', 3)
            ->where('completedByWeek.series.0.values.7', 3)
            ->where('completedByWeek.series.1.key', 'BBB')
            ->where('completedByWeek.series.1.values.6', 1)
            ->where('completedByWeek.weekTotals.7', 3)
            ->where('completedByWeek.weekTotals.6', 1)
            ->where('completedByWeek.grandTotal', 4)
        );
});

it('rolls completed projects beyond the top six into an Other series', function () {
    $projects = collect(range(1, 7))
        ->map(fn (int $n): Project => Project::factory()->create(['key' => "P{$n}"]));

    $projects->each(fn (Project $project) => Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now(),
    ]));

    $this->actingAs(member($projects->all()))
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('completedByWeek.series', 7)
            ->where('completedByWeek.series.6.other', true)
            ->where('completedByWeek.series.6.name', 'Other (1)')
            ->where('completedByWeek.grandTotal', 7)
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
            ->has('completedByWeek.weekTotals', 8)
            ->where('metrics.completed', 1)
            ->where('metrics.wip', 3)
        );
});

it('summarises time logged this week and estimate accuracy', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    $done = Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now(),
        'estimate_minutes' => 120,
    ]);
    TimeEntry::factory()->for($done)->create(['minutes' => 180, 'spent_on' => now()]);

    $wip = Issue::factory()->for($project)->create(['status' => IssueStatus::InProgress]);
    TimeEntry::factory()->for($wip)->create(['minutes' => 60, 'spent_on' => now()->subWeek()]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('time.loggedThisWeek', 180)
            ->where('time.loggedPreviousWeek', 60)
            ->where('time.loggedByProject.0.key', 'THI')
            ->where('time.loggedByProject.0.minutes', 180)
            ->where('time.accuracy.pct', 50)
            ->where('time.accuracy.overPct', 50)
            ->where('time.accuracy.direction', 'over')
            ->where('time.accuracy.sampleSize', 1)
        );
});

it('counts issue statuses in a single grouped query', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project);

    DB::enableQueryLog();
    $this->actingAs($user)->get('/dashboard')->assertOk();

    $grouped = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'group by "status"'));

    expect($grouped)->toHaveCount(1);

    DB::disableQueryLog();
});
