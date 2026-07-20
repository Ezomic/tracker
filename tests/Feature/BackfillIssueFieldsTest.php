<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;

function runBackfillMigration(): void
{
    (require base_path('database/migrations/2026_07_20_120000_backfill_issue_priority_assignee_estimate_and_labels.php'))->up();
}

/** TRACK-83 is a real row in the backfill table: frontend, medium, 45m. */
function trackedIssue(Project $project): Issue
{
    return Issue::factory()->for($project)->create([
        'identifier' => 'TRACK-83',
        'number' => 83,
        'type' => IssueType::Fix,
        'priority' => IssuePriority::None,
        'assignee_id' => null,
        'estimate_minutes' => null,
    ]);
}

it('fills priority, estimate, assignee and labels on an untouched issue', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    Label::factory()->for($org)->create(['name' => 'frontend']);
    $issue = trackedIssue($project);

    runBackfillMigration();

    expect($issue->fresh())
        ->priority->toBe(IssuePriority::Medium)
        ->estimate_minutes->toBe(45)
        ->assignee_id->toBe($owner->id)
        ->and($issue->fresh()->labels->pluck('name')->all())->toBe(['frontend']);
});

it('does not overwrite a priority or estimate that was already set', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    $issue = trackedIssue($project);
    $issue->forceFill(['priority' => IssuePriority::Urgent, 'estimate_minutes' => 999])->save();

    runBackfillMigration();

    expect($issue->fresh())
        ->priority->toBe(IssuePriority::Urgent)
        ->estimate_minutes->toBe(999);
});

it('leaves curated labels alone', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    Label::factory()->for($org)->create(['name' => 'frontend']);
    $curated = Label::factory()->for($org)->create(['name' => 'curated']);
    $issue = trackedIssue($project);
    $issue->labels()->attach($curated);

    runBackfillMigration();

    expect($issue->fresh()->labels->pluck('name')->all())->toBe(['curated']);
});

it('skips the assignee when the project has more than one member', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    $project->members()->attach(User::factory()->create(), ['level' => 'write']);
    $issue = trackedIssue($project);

    runBackfillMigration();

    expect($issue->fresh())
        ->assignee_id->toBeNull()
        ->priority->toBe(IssuePriority::Medium);
});

it('is idempotent when run twice', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    Label::factory()->for($org)->create(['name' => 'frontend']);
    $issue = trackedIssue($project);

    runBackfillMigration();
    runBackfillMigration();

    expect($issue->fresh()->labels)->toHaveCount(1)
        ->and($issue->fresh()->estimate_minutes)->toBe(45);
});

it('ignores identifiers that do not exist', function () {
    [$org, $owner] = organizationWith();
    projectInOrganization($org, $owner, ['key' => 'TRACK']);

    runBackfillMigration();

    expect(Issue::query()->count())->toBe(0);
});
