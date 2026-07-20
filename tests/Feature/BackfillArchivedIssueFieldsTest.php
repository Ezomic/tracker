<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;

function runArchivedBackfillMigration(): void
{
    (require base_path('database/migrations/2026_07_20_140000_backfill_archived_issue_fields.php'))->up();
}

/** TRACK-1 is a real row in the archived backfill table: database, high, 6h. */
function archivedIssue(Project $project): Issue
{
    return Issue::factory()->for($project)->create([
        'identifier' => 'TRACK-1',
        'number' => 1,
        'type' => IssueType::Feature,
        'priority' => IssuePriority::None,
        'assignee_id' => null,
        'estimate_minutes' => null,
        'archived_at' => now(),
    ]);
}

it('backfills an archived issue', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    Label::factory()->for($org)->create(['name' => 'database']);
    $issue = archivedIssue($project);

    runArchivedBackfillMigration();

    expect($issue->fresh())
        ->priority->toBe(IssuePriority::High)
        ->estimate_minutes->toBe(360)
        ->assignee_id->toBe($owner->id)
        ->archived_at->not->toBeNull()
        ->and($issue->fresh()->labels->pluck('name')->all())->toBe(['database']);
});

it('leaves an already-set priority and estimate alone', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    $issue = archivedIssue($project);
    $issue->forceFill(['priority' => IssuePriority::Low, 'estimate_minutes' => 15])->save();

    runArchivedBackfillMigration();

    expect($issue->fresh())
        ->priority->toBe(IssuePriority::Low)
        ->estimate_minutes->toBe(15);
});

it('does not unarchive anything it touches', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    $issue = archivedIssue($project);

    runArchivedBackfillMigration();

    expect($issue->fresh()->archived_at)->not->toBeNull();
});

it('skips the assignee on a multi-member project', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    $project->members()->attach(User::factory()->create(), ['level' => 'write']);
    $issue = archivedIssue($project);

    runArchivedBackfillMigration();

    expect($issue->fresh()->assignee_id)->toBeNull();
});

it('is idempotent when run twice', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'TRACK']);
    Label::factory()->for($org)->create(['name' => 'database']);
    $issue = archivedIssue($project);

    runArchivedBackfillMigration();
    runArchivedBackfillMigration();

    expect($issue->fresh()->labels)->toHaveCount(1);
});
