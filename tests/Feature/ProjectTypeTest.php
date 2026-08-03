<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\WorkflowState;

it('relates project types, states, projects and issues', function () {
    $type = ProjectType::factory()->create();
    $backlog = WorkflowState::factory()->for($type)->create([
        'name' => 'Backlog',
        'category' => StatusCategory::Backlog,
        'position' => 0,
    ]);
    $project = Project::factory()->create(['project_type_id' => $type->id]);
    $issue = Issue::factory()->for($project)->create(['workflow_state_id' => $backlog->id]);

    expect($type->states)->toHaveCount(1)
        ->and($type->states->first()->category)->toBe(StatusCategory::Backlog)
        ->and($project->projectType->is($type))->toBeTrue()
        ->and($issue->workflowState->is($backlog))->toBeTrue()
        ->and($backlog->projectType->is($type))->toBeTrue();
});

it('orders a project type states by position', function () {
    $type = ProjectType::factory()->create();
    WorkflowState::factory()->for($type)->create(['name' => 'Done', 'position' => 3]);
    WorkflowState::factory()->for($type)->create(['name' => 'Backlog', 'position' => 0]);

    expect($type->states->pluck('name')->all())->toBe(['Backlog', 'Done']);
});

it('seeds a default type per organization and backfills issue states', function () {
    [$org, $user] = organizationWith();
    $project = projectInOrganization($org, $user, ['key' => 'ABC']);

    $backlog = Issue::factory()->for($project)->create(['status' => IssueStatus::Backlog]);
    $inProgress = Issue::factory()->for($project)->create(['status' => IssueStatus::InProgress]);
    $inReview = Issue::factory()->for($project)->create(['status' => IssueStatus::InReview]);
    $done = Issue::factory()->for($project)->create(['status' => IssueStatus::Done, 'closed_at' => now()]);

    $migration = require database_path('migrations/2026_08_02_100100_seed_default_project_types_and_backfill.php');
    $migration->up();

    $type = ProjectType::query()
        ->where('organization_id', $org->id)
        ->where('is_default', true)
        ->first();

    expect($type)->not->toBeNull()
        ->and($type->states)->toHaveCount(4)
        ->and($type->states->firstWhere('name', 'Backlog')->is_default)->toBeTrue()
        ->and($type->states->firstWhere('name', 'Done')->is_default)->toBeFalse()
        ->and($project->fresh()->project_type_id)->toBe($type->id)
        ->and($backlog->fresh()->workflowState->category)->toBe(StatusCategory::Backlog)
        ->and($inProgress->fresh()->workflowState->name)->toBe('In Progress')
        ->and($inProgress->fresh()->workflowState->category)->toBe(StatusCategory::Started)
        ->and($inReview->fresh()->workflowState->category)->toBe(StatusCategory::Started)
        ->and($done->fresh()->workflowState->category)->toBe(StatusCategory::Completed);
});

it('is a no-op when every project already has a type', function () {
    $type = ProjectType::factory()->create();
    Project::factory()->create(['project_type_id' => $type->id]);

    $before = ProjectType::query()->count();

    $migration = require database_path('migrations/2026_08_02_100100_seed_default_project_types_and_backfill.php');
    $migration->up();

    expect(ProjectType::query()->count())->toBe($before);
});
