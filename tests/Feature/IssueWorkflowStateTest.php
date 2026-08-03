<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\ProjectType;
use App\Models\WorkflowState;

/**
 * @return array<string, mixed>
 */
function typedProject(): array
{
    [$org, $user] = organizationWith();

    $type = ProjectType::factory()->for($org)->create(['is_default' => true]);

    $lanes = collect([
        ['Backlog', StatusCategory::Backlog, 0],
        ['In Progress', StatusCategory::Started, 1],
        ['In Review', StatusCategory::Started, 2],
        ['Done', StatusCategory::Completed, 3],
    ])->mapWithKeys(fn (array $lane): array => [
        $lane[0] => WorkflowState::factory()->for($type)->create([
            'name' => $lane[0],
            'category' => $lane[1],
            'position' => $lane[2],
        ]),
    ]);

    $project = projectInOrganization($org, $user, ['key' => 'ABC', 'project_type_id' => $type->id]);

    return ['user' => $user, 'type' => $type, 'project' => $project, 'lanes' => $lanes];
}

it('moves an issue to a workflow state, syncing status and closed_at', function () {
    ['user' => $user, 'project' => $project, 'lanes' => $lanes] = typedProject();
    $issue = Issue::factory()->for($project)->create(['status' => IssueStatus::Backlog]);

    $this->actingAs($user)
        ->patch("/issues/{$issue->identifier}/state", ['workflow_state_id' => $lanes['In Review']->id])
        ->assertRedirect();

    $issue->refresh();
    expect($issue->workflow_state_id)->toBe($lanes['In Review']->id)
        ->and($issue->status)->toBe(IssueStatus::InReview) // second started lane
        ->and($issue->closed_at)->toBeNull();

    $this->actingAs($user)->patch("/issues/{$issue->identifier}/state", ['workflow_state_id' => $lanes['Done']->id]);
    $issue->refresh();
    expect($issue->status)->toBe(IssueStatus::Done)
        ->and($issue->closed_at)->not->toBeNull();

    $this->actingAs($user)->patch("/issues/{$issue->identifier}/state", ['workflow_state_id' => $lanes['Backlog']->id]);
    expect($issue->fresh()->closed_at)->toBeNull();
});

it('maps the first started lane to in progress', function () {
    ['user' => $user, 'project' => $project, 'lanes' => $lanes] = typedProject();
    $issue = Issue::factory()->for($project)->create(['status' => IssueStatus::Backlog]);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}/state", ['workflow_state_id' => $lanes['In Progress']->id]);

    expect($issue->fresh()->status)->toBe(IssueStatus::InProgress);
});

it('rejects a state that belongs to another project type', function () {
    ['user' => $user, 'project' => $project] = typedProject();
    $otherState = WorkflowState::factory()->for(ProjectType::factory())->create();
    $issue = Issue::factory()->for($project)->create();

    $this->actingAs($user)
        ->patch("/issues/{$issue->identifier}/state", ['workflow_state_id' => $otherState->id])
        ->assertSessionHasErrors('workflow_state_id');

    expect($issue->fresh()->workflow_state_id)->toBeNull();
});

it('exposes the project type lanes in the board payload', function () {
    ['user' => $user] = typedProject();

    $this->actingAs($user)
        ->get('/issues/board')
        ->assertInertia(fn ($page) => $page
            ->has('workflowStates', 4)
            ->where('workflowStates.0.name', 'Backlog')
            ->where('workflowStates.3.category', 'completed')
        );
});
