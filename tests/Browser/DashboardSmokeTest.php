<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

/**
 * A representative dashboard: a few projects with completions spread over the
 * window (so the chart and matrix have real rows), plus an urgent open issue
 * for the attention list.
 */
function seedSmokeDashboard(): User
{
    $projects = collect(['ALPHA', 'BETA', 'GAMMA'])
        ->map(fn (string $key): Project => Project::factory()->create(['key' => $key, 'name' => ucfirst(strtolower($key)).' project']));

    $user = member($projects->all());

    $plan = [
        'ALPHA' => [0, 2, 1, 3, 2, 4, 1, 3],
        'BETA' => [1, 0, 2, 1, 0, 2, 3, 1],
        'GAMMA' => [0, 1, 0, 1, 1, 0, 2, 1],
    ];

    foreach ($plan as $key => $weeks) {
        $project = $projects->firstWhere('key', $key);

        foreach ($weeks as $i => $count) {
            $closed = now()->startOfWeek()->subWeeks(7 - $i)->addDays(2)->setTime(12, 0);

            for ($n = 0; $n < $count; $n++) {
                Issue::factory()->for($project)->create([
                    'status' => IssueStatus::Done,
                    'closed_at' => $closed,
                    'created_at' => $closed->copy()->subDays(3),
                    'owner_id' => $user->id,
                ]);
            }
        }
    }

    $alpha = $projects->firstWhere('key', 'ALPHA');
    Issue::factory()->for($alpha)->create([
        'status' => IssueStatus::InProgress,
        'priority' => IssuePriority::Urgent,
        'assignee_id' => $user->id,
        'title' => 'Urgent open item needing attention',
    ]);

    return $user;
}

it('renders the unified dashboard for a signed-in user', function () {
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Acme Software']);
    $user = member($project);
    Issue::factory()->for($project)->count(2)->create(['status' => IssueStatus::Backlog]);
    Issue::factory()->for($project)->create(['status' => IssueStatus::InReview]);
    Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now(),
    ]);

    $this->actingAs($user);

    $page = visit('/dashboard');

    $page->assertSee('Needs your attention')
        ->assertSee('Active tickets by project')
        ->assertSee('Completed per week')
        ->assertSee('Tickets done by project, week by week')
        ->assertDontSee("This week's time");

    $page->assertNoJavascriptErrors();
});

it('renders without horizontal overflow on a mobile viewport', function () {
    $this->actingAs(seedSmokeDashboard());

    $page = visit('/dashboard')->resize(375, 812);

    $page->assertSee('Completed per week')
        ->assertSee('Tickets done by project, week by week')
        ->assertNoJavascriptErrors()
        ->screenshot(filename: 'dashboard-mobile');

    // The page body must not scroll sideways; wide content scrolls in its own box.
    $overflow = $page->script('document.documentElement.scrollWidth - document.documentElement.clientWidth');
    expect($overflow)->toBeLessThanOrEqual(1);
});

it('renders cleanly on a tablet viewport', function () {
    $this->actingAs(seedSmokeDashboard());

    $page = visit('/dashboard')->resize(768, 1024);

    $page->assertSee('Completed per week')
        ->assertNoJavascriptErrors()
        ->screenshot(filename: 'dashboard-tablet');

    $overflow = $page->script('document.documentElement.scrollWidth - document.documentElement.clientWidth');
    expect($overflow)->toBeLessThanOrEqual(1);
});
