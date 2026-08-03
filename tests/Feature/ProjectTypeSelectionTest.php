<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\ProjectType;
use App\Models\WorkflowState;

function projectTypeWithLanes(object $org, string $name): ProjectType
{
    $type = ProjectType::factory()->for($org)->create(['name' => $name]);

    WorkflowState::factory()->for($type)->create(['name' => $name.' todo', 'category' => StatusCategory::Backlog, 'position' => 0, 'is_default' => true]);
    WorkflowState::factory()->for($type)->create(['name' => $name.' doing', 'category' => StatusCategory::Started, 'position' => 1]);
    WorkflowState::factory()->for($type)->create(['name' => $name.' done', 'category' => StatusCategory::Completed, 'position' => 2]);

    return $type->refresh();
}

it('offers the organization types on the projects page', function () {
    [$org, $user] = organizationWith();
    $type = projectTypeWithLanes($org, 'Engineering');
    $project = projectInOrganization($org, $user, ['key' => 'ABC', 'project_type_id' => $type->id]);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Index')
            ->where('projectTypes.0.name', 'Engineering')
            ->where('projects.0.projectTypeId', $project->project_type_id)
        );
});

it('moves issues onto the matching lane of the new type', function () {
    [$org, $user] = organizationWith();
    $from = projectTypeWithLanes($org, 'From');
    $to = projectTypeWithLanes($org, 'To');

    $project = projectInOrganization($org, $user, ['key' => 'ABC', 'project_type_id' => $from->id]);
    $doing = $from->states->firstWhere('category', StatusCategory::Started);
    $issue = Issue::factory()->for($project)->create(['workflow_state_id' => $doing->id]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), [
            'name' => $project->name,
            'project_type_id' => $to->id,
        ])
        ->assertRedirect(route('projects.index'));

    expect($project->fresh()->project_type_id)->toBe($to->id)
        ->and($issue->fresh()->workflow_state_id)->toBe($to->states->firstWhere('category', StatusCategory::Started)->id)
        ->and($issue->fresh()->status)->toBe(IssueStatus::InProgress);
});

it('falls back to the default lane when the new type has no matching category', function () {
    [$org, $user] = organizationWith();
    $from = projectTypeWithLanes($org, 'From');

    $to = ProjectType::factory()->for($org)->create(['name' => 'Flat']);
    $only = WorkflowState::factory()->for($to)->create(['name' => 'Inbox', 'category' => StatusCategory::Backlog, 'position' => 0, 'is_default' => true]);

    $project = projectInOrganization($org, $user, ['key' => 'ABC', 'project_type_id' => $from->id]);
    $issue = Issue::factory()->for($project)->create([
        'workflow_state_id' => $from->states->firstWhere('category', StatusCategory::Completed)->id,
    ]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), [
            'name' => $project->name,
            'project_type_id' => $to->id,
        ])
        ->assertRedirect(route('projects.index'));

    expect($issue->fresh()->workflow_state_id)->toBe($only->id)
        ->and($issue->fresh()->status)->toBe(IssueStatus::Backlog);
});

it('clears lane references when the project type is removed', function () {
    [$org, $user] = organizationWith();
    $from = projectTypeWithLanes($org, 'From');

    $project = projectInOrganization($org, $user, ['key' => 'ABC', 'project_type_id' => $from->id]);
    $issue = Issue::factory()->for($project)->create([
        'workflow_state_id' => $from->states->firstWhere('category', StatusCategory::Backlog)->id,
    ]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), [
            'name' => $project->name,
            'project_type_id' => null,
        ])
        ->assertRedirect(route('projects.index'));

    expect($project->fresh()->project_type_id)->toBeNull()
        ->and($issue->fresh()->workflow_state_id)->toBeNull();
});

it('leaves the type untouched when the request omits it', function () {
    [$org, $user] = organizationWith();
    $type = projectTypeWithLanes($org, 'Engineering');
    $project = projectInOrganization($org, $user, ['key' => 'ABC', 'project_type_id' => $type->id]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), [
            'name' => 'Renamed',
            'key' => $project->key,
        ])
        ->assertRedirect(route('projects.index'));

    expect($project->fresh()->project_type_id)->toBe($type->id)
        ->and($project->fresh()->name)->toBe('Renamed');
});

it('rejects a type from another organization', function () {
    [$org, $user] = organizationWith();
    [$otherOrg] = organizationWith();
    $foreign = projectTypeWithLanes($otherOrg, 'Foreign');

    $project = projectInOrganization($org, $user, ['key' => 'ABC']);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), [
            'name' => $project->name,
            'key' => $project->key,
            'project_type_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('project_type_id');

    expect($project->fresh()->project_type_id)->not->toBe($foreign->id);
});
