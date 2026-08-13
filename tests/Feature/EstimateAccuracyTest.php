<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Support\EstimateAccuracy;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI', 'name' => 'Tracker']);
    $this->user = member($this->project);
});

function closedWith(Project $project, int $estimate, int $logged, array $attributes = []): Issue
{
    $issue = Issue::factory()->for($project)->create([
        'status' => IssueStatus::Done,
        'closed_at' => now()->subDays(2),
        'estimate_minutes' => $estimate,
        ...$attributes,
    ]);

    if ($logged > 0) {
        TimeEntry::factory()->for($issue)->create(['minutes' => $logged]);
    }

    return $issue;
}

it('reports estimated against actual and the ratio', function () {
    closedWith($this->project, 60, 90);
    closedWith($this->project, 60, 30);

    $result = EstimateAccuracy::build($this->user, 12);

    expect($result['overall']['estimated'])->toBe(120)
        ->and($result['overall']['actual'])->toBe(120)
        ->and($result['overall']['ratio'])->toBe(1.0)
        ->and($result['overall']['direction'])->toBe('none')
        ->and($result['overall']['sampleSize'])->toBe(2);
});

it('calls it over when actuals exceed estimates', function () {
    closedWith($this->project, 60, 120);

    $result = EstimateAccuracy::build($this->user, 12);

    expect($result['overall']['ratio'])->toBe(2.0)
        ->and($result['overall']['direction'])->toBe('over');
});

it('calls it under when actuals come in below', function () {
    closedWith($this->project, 100, 50);

    expect(EstimateAccuracy::build($this->user, 12)['overall']['direction'])->toBe('under');
});

it('counts only issues carrying both an estimate and logged time', function () {
    closedWith($this->project, 60, 60);
    closedWith($this->project, 60, 0);

    $result = EstimateAccuracy::build($this->user, 12);

    expect($result['overall']['sampleSize'])->toBe(1);
});

it('says how many were excluded, so a thin sample cannot hide', function () {
    closedWith($this->project, 60, 60);
    closedWith($this->project, 60, 0);
    closedWith($this->project, 90, 0);

    expect(EstimateAccuracy::build($this->user, 12)['overall']['excluded'])->toBe(2);
});

it('breaks down per project', function () {
    $other = Project::factory()->create(['key' => 'CMS', 'name' => 'Portfolio CMS']);
    joinProjects($this->user, $other);
    closedWith($this->project, 60, 120);
    closedWith($other, 60, 60);
    closedWith($other, 60, 60);

    $projects = EstimateAccuracy::build($this->user, 12)['projects'];

    expect($projects)->toHaveCount(2)
        // Biggest sample first, so the most trustworthy row leads.
        ->and($projects[0]['key'])->toBe('CMS')
        ->and($projects[0]['sampleSize'])->toBe(2)
        ->and($projects[1]['key'])->toBe('THI')
        ->and($projects[1]['direction'])->toBe('over');
});

it('breaks down by estimate size', function () {
    closedWith($this->project, 30, 30);
    closedWith($this->project, 120, 240);
    closedWith($this->project, 600, 600);

    $bands = collect(EstimateAccuracy::build($this->user, 12)['bands'])->keyBy('band');

    expect($bands['under_1h']['sampleSize'])->toBe(1)
        ->and($bands['1h_to_4h']['sampleSize'])->toBe(1)
        ->and($bands['1h_to_4h']['direction'])->toBe('over')
        ->and($bands['over_4h']['sampleSize'])->toBe(1);
});

it('puts a 60 minute estimate in the under-1h band, not the next one', function () {
    closedWith($this->project, 60, 60);

    $bands = collect(EstimateAccuracy::build($this->user, 12)['bands'])->keyBy('band');

    expect($bands['under_1h']['sampleSize'])->toBe(1)
        ->and($bands['1h_to_4h']['sampleSize'])->toBe(0);
});

it('honours the window', function () {
    closedWith($this->project, 60, 60, ['closed_at' => now()->subWeeks(20)]);

    expect(EstimateAccuracy::build($this->user, 4)['overall']['sampleSize'])->toBe(0)
        ->and(EstimateAccuracy::build($this->user, 26)['overall']['sampleSize'])->toBe(1);
});

it('ignores issues that are not done', function () {
    closedWith($this->project, 60, 60, ['status' => IssueStatus::InProgress, 'closed_at' => null]);

    expect(EstimateAccuracy::build($this->user, 12)['overall']['sampleSize'])->toBe(0);
});

it('never leaks another members projects', function () {
    $other = Project::factory()->create(['key' => 'SECRET']);
    closedWith($other, 60, 60);

    expect(EstimateAccuracy::build($this->user, 12)['overall']['sampleSize'])->toBe(0);
});

it('returns a null ratio rather than dividing by zero', function () {
    $result = EstimateAccuracy::build($this->user, 12);

    expect($result['overall']['ratio'])->toBeNull()
        ->and($result['overall']['direction'])->toBe('none');
});

it('reaches the dashboard', function () {
    closedWith($this->project, 60, 120);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('accuracy.window', 12)
            ->where('accuracy.overall.direction', 'over')
            ->has('accuracy.projects', 1)
            ->has('accuracy.bands', 3));
});

it('takes the window from the request', function () {
    $this->actingAs($this->user)
        ->get('/dashboard?accuracy_weeks=26')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('accuracy.window', 26));
});

it('falls back to 12 weeks for a nonsense window rather than erroring', function () {
    $this->actingAs($this->user)
        ->get('/dashboard?accuracy_weeks=999')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('accuracy.window', 12));
});
