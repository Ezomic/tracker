<?php

declare(strict_types=1);

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
