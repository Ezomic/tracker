<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\WorkflowState;
use Illuminate\Support\Facades\DB;

/**
 * The migration is guarded and no-ops on the test database's normal state, so
 * these run it by hand against rows built to look like the drifted production
 * data it exists to repair.
 */
function runBackfill(): void
{
    $migration = require database_path('migrations/2026_08_07_090000_backfill_issue_workflow_states.php');
    $migration->up();
}

beforeEach(function () {
    $this->type = ProjectType::factory()->create();
    $this->lanes = collect([
        ['name' => 'Backlog', 'category' => StatusCategory::Backlog, 'position' => 1],
        ['name' => 'In progress', 'category' => StatusCategory::Started, 'position' => 2],
        ['name' => 'In review', 'category' => StatusCategory::Started, 'position' => 3],
        ['name' => 'Done', 'category' => StatusCategory::Completed, 'position' => 4],
    ])->mapWithKeys(fn (array $l): array => [
        $l['name'] => WorkflowState::factory()->for($this->type, 'projectType')->create($l),
    ]);

    $this->project = Project::factory()->create(['key' => 'THI', 'project_type_id' => $this->type->id]);
});

function drifted(Project $project, IssueStatus $status, ?int $laneId): Issue
{
    $issue = Issue::factory()->for($project)->create(['status' => $status]);

    DB::table('issues')->where('id', $issue->id)->update(['workflow_state_id' => $laneId]);

    return $issue->refresh();
}

it('gives a lane to an issue that has none', function () {
    $issue = drifted($this->project, IssueStatus::Done, null);

    runBackfill();

    expect($issue->refresh()->workflow_state_id)->toBe($this->lanes['Done']->id);
});

it('corrects a done issue stranded in the backlog lane', function () {
    $issue = drifted($this->project, IssueStatus::Done, $this->lanes['Backlog']->id);

    runBackfill();

    expect($issue->refresh()->workflow_state_id)->toBe($this->lanes['Done']->id);
});

it('maps every legacy status onto the right lane', function (IssueStatus $status, string $lane) {
    $issue = drifted($this->project, $status, null);

    runBackfill();

    expect($issue->refresh()->workflow_state_id)->toBe($this->lanes[$lane]->id);
})->with([
    [IssueStatus::Backlog, 'Backlog'],
    [IssueStatus::InProgress, 'In progress'],
    [IssueStatus::InReview, 'In review'],
    [IssueStatus::Done, 'Done'],
]);

it('leaves an equivalent lane alone rather than rewriting it', function () {
    // In review already means started, which is what in_progress means too:
    // rewriting it would move the issue on the board for no reason.
    $issue = drifted($this->project, IssueStatus::InProgress, $this->lanes['In review']->id);

    runBackfill();

    expect($issue->refresh()->workflow_state_id)->toBe($this->lanes['In review']->id);
});

it('never touches status or closed_at', function () {
    $issue = drifted($this->project, IssueStatus::Done, $this->lanes['Backlog']->id);
    $closedAt = $issue->closed_at;

    runBackfill();

    $fresh = $issue->refresh();
    expect($fresh->status)->toBe(IssueStatus::Done)
        ->and($fresh->closed_at?->toIso8601String())->toBe($closedAt?->toIso8601String());
});

it('leaves issues in projects with no type without a lane', function () {
    $untyped = Project::factory()->create(['key' => 'RAW', 'project_type_id' => null]);
    $issue = drifted($untyped, IssueStatus::Done, null);

    runBackfill();

    expect($issue->refresh()->workflow_state_id)->toBeNull();
});

it('does not reach across into another project type lanes', function () {
    $otherType = ProjectType::factory()->create();
    WorkflowState::factory()->for($otherType, 'projectType')->create([
        'name' => 'Shipped', 'category' => StatusCategory::Completed, 'position' => 1,
    ]);
    $issue = drifted($this->project, IssueStatus::Done, null);

    runBackfill();

    expect($issue->refresh()->workflowState->project_type_id)->toBe($this->type->id);
});

it('handles a type whose only started lane serves both started statuses', function () {
    $simple = ProjectType::factory()->create();
    $doing = WorkflowState::factory()->for($simple, 'projectType')->create([
        'name' => 'Doing', 'category' => StatusCategory::Started, 'position' => 1,
    ]);
    $project = Project::factory()->create(['key' => 'SIM', 'project_type_id' => $simple->id]);
    $issue = drifted($project, IssueStatus::InReview, null);

    runBackfill();

    expect($issue->refresh()->workflow_state_id)->toBe($doing->id);
});

it('leaves a status with no matching lane alone rather than guessing', function () {
    $partial = ProjectType::factory()->create();
    WorkflowState::factory()->for($partial, 'projectType')->create([
        'name' => 'Only backlog', 'category' => StatusCategory::Backlog, 'position' => 1,
    ]);
    $project = Project::factory()->create(['key' => 'PART', 'project_type_id' => $partial->id]);
    $issue = drifted($project, IssueStatus::Done, null);

    runBackfill();

    expect($issue->refresh()->workflow_state_id)->toBeNull();
});

it('is idempotent', function () {
    $issue = drifted($this->project, IssueStatus::Done, $this->lanes['Backlog']->id);

    runBackfill();
    $afterFirst = $issue->refresh()->workflow_state_id;
    runBackfill();

    expect($issue->refresh()->workflow_state_id)->toBe($afterFirst);
});
