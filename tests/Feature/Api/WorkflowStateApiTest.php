<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\WorkflowState;

beforeEach(function () {
    $this->type = ProjectType::factory()->create();
    $this->lanes = collect([
        ['name' => 'Backlog', 'category' => StatusCategory::Backlog, 'position' => 1, 'is_default' => true],
        ['name' => 'In progress', 'category' => StatusCategory::Started, 'position' => 2, 'is_default' => false],
        ['name' => 'In review', 'category' => StatusCategory::Started, 'position' => 3, 'is_default' => false],
        ['name' => 'Shipped', 'category' => StatusCategory::Completed, 'position' => 4, 'is_default' => false],
    ])->mapWithKeys(fn (array $lane): array => [
        $lane['name'] => WorkflowState::factory()->for($this->type, 'projectType')->create($lane),
    ]);

    $this->project = Project::factory()->create(['key' => 'THI', 'project_type_id' => $this->type->id]);
    $this->user = member($this->project);
});

it('reports the lane an issue sits in', function () {
    $issue = Issue::factory()->for($this->project)->create([
        'identifier' => 'THI-1',
        'workflow_state_id' => $this->lanes['In progress']->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/issues/{$issue->identifier}");

    $response->assertOk()->assertJsonPath('workflow_state', [
        'id' => $this->lanes['In progress']->id,
        'name' => 'In progress',
        'category' => 'started',
    ]);
});

it('reports the lane on the list too', function () {
    Issue::factory()->for($this->project)->create([
        'identifier' => 'THI-1',
        'workflow_state_id' => $this->lanes['Shipped']->id,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues');

    expect($response->json('data.0.workflow_state.name'))->toBe('Shipped')
        ->and($response->json('data.0.workflow_state.category'))->toBe('completed');
});

it('keeps status alongside the lane rather than replacing it', function () {
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['workflow_state' => 'Shipped']);

    $response->assertOk();
    $fresh = $issue->fresh();
    expect($fresh->workflow_state_id)->toBe($this->lanes['Shipped']->id)
        ->and($fresh->status)->toBe(IssueStatus::Done)
        ->and($fresh->closed_at)->not->toBeNull();
});

it('accepts a lane by id as well as by name', function () {
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['workflow_state' => (string) $this->lanes['In review']->id])
        ->assertOk();

    expect($issue->fresh()->workflow_state_id)->toBe($this->lanes['In review']->id);
});

it('matches a lane name case-insensitively', function () {
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['workflow_state' => 'in PROGRESS'])
        ->assertOk();

    expect($issue->fresh()->workflow_state_id)->toBe($this->lanes['In progress']->id);
});

it('moves the lane when the legacy status is sent, instead of leaving it stale', function (string $status, string $lane) {
    $issue = Issue::factory()->for($this->project)->create([
        'identifier' => 'THI-1',
        'workflow_state_id' => $this->lanes['Backlog']->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['status' => $status])
        ->assertOk();

    $fresh = $issue->fresh();
    expect($fresh->workflow_state_id)->toBe($this->lanes[$lane]->id)
        ->and($fresh->status->value)->toBe($status);
})->with([
    ['in_progress', 'In progress'],
    ['in_review', 'In review'],
    ['done', 'Shipped'],
    ['backlog', 'Backlog'],
]);

it('round-trips: a lane set by name reads back as the matching legacy status', function () {
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['workflow_state' => 'In review'])
        ->assertOk();

    $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/issues/{$issue->identifier}");

    expect($response->json('status'))->toBe('in_review')
        ->and($response->json('workflow_state.name'))->toBe('In review');
});

it('refuses a lane belonging to another project type', function () {
    $otherType = ProjectType::factory()->create();
    $foreign = WorkflowState::factory()->for($otherType, 'projectType')->create(['name' => 'Elsewhere']);
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['workflow_state' => (string) $foreign->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('workflow_state');

    expect($issue->fresh()->workflow_state_id)->not->toBe($foreign->id);
});

it('refuses an unknown lane name', function () {
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['workflow_state' => 'Nowhere'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('workflow_state');
});

it('requires one of the two forms', function () {
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('refuses both forms at once rather than picking one', function () {
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", [
            'status' => 'done',
            'workflow_state' => 'Backlog',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('still honours the legacy status on a project with no type', function () {
    $untyped = Project::factory()->create(['key' => 'RAW', 'project_type_id' => null]);
    $user = member($untyped);
    $issue = Issue::factory()->for($untyped)->create(['identifier' => 'RAW-1']);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['status' => 'done'])
        ->assertOk();

    $fresh = $issue->fresh();
    expect($fresh->status)->toBe(IssueStatus::Done)
        ->and($fresh->closed_at)->not->toBeNull()
        ->and($fresh->workflow_state_id)->toBeNull();
});

it('reports a null lane for an untyped project rather than failing', function () {
    $untyped = Project::factory()->create(['key' => 'RAW', 'project_type_id' => null]);
    $user = member($untyped);
    $issue = Issue::factory()->for($untyped)->create(['identifier' => 'RAW-1']);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/issues/{$issue->identifier}")
        ->assertOk()
        ->assertJsonPath('workflow_state', null);
});

it('starts a newly filed issue in the type default lane', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Filed over the API',
        'type' => 'fix',
    ]);

    $response->assertCreated();
    $issue = Issue::query()->firstOrFail();
    expect($issue->workflow_state_id)->toBe($this->lanes['Backlog']->id);
});

it('filters the list by lane name', function () {
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'workflow_state_id' => $this->lanes['Shipped']->id]);
    Issue::factory()->for($this->project)->create(['number' => 2, 'identifier' => 'THI-2', 'workflow_state_id' => $this->lanes['Backlog']->id]);

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues?workflow_state=shipped');

    expect($response->json('data.*.identifier'))->toBe(['THI-1']);
});

it('filters the list by state category, which survives renaming a lane', function () {
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'workflow_state_id' => $this->lanes['In progress']->id]);
    Issue::factory()->for($this->project)->create(['number' => 2, 'identifier' => 'THI-2', 'workflow_state_id' => $this->lanes['Shipped']->id]);

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues?state_category=completed');

    expect($response->json('data.*.identifier'))->toBe(['THI-2']);
});

it('rejects an unknown state category', function () {
    $this->actingAs($this->user, 'sanctum')->getJson('/api/issues?state_category=nonsense')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('state_category');
});

it('collapses both started statuses onto the single started lane of a simpler type', function () {
    $simple = ProjectType::factory()->create();
    WorkflowState::factory()->for($simple, 'projectType')->create(['name' => 'Todo', 'category' => StatusCategory::Backlog, 'position' => 1]);
    $doing = WorkflowState::factory()->for($simple, 'projectType')->create(['name' => 'Doing', 'category' => StatusCategory::Started, 'position' => 2]);
    $project = Project::factory()->create(['key' => 'SIM', 'project_type_id' => $simple->id]);
    $user = member($project);
    $issue = Issue::factory()->for($project)->create(['identifier' => 'SIM-1']);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['status' => 'in_review'])
        ->assertOk();

    expect($issue->fresh()->workflow_state_id)->toBe($doing->id);
});

it('announces when the legacy status form stops being accepted', function () {
    $issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}/status", ['workflow_state' => 'Backlog']);

    $response->assertOk();
    expect($response->headers->get('Sunset'))->toBe('Wed, 30 Sep 2026 00:00:00 GMT')
        ->and($response->headers->get('Deprecation'))->toBe('true');
});
