<?php

declare(strict_types=1);

use App\Actions\CreateProjectAction;
use App\Enums\OrganizationRole;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\ProjectType;
use App\Models\WorkflowState;

it('lists the organization project types', function () {
    [$org, $user] = organizationWith();
    $type = ProjectType::factory()->for($org)->create(['name' => 'Engineering', 'is_default' => true]);
    WorkflowState::factory()->for($type)->create(['name' => 'Backlog']);

    $this->actingAs($user)
        ->get(route('project-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/ProjectTypes')
            ->where('types.0.name', 'Engineering')
            ->where('types.0.states.0.name', 'Backlog')
            ->has('categories', 5)
        );
});

it('creates a project type with lanes', function () {
    [$org, $user] = organizationWith();

    $this->actingAs($user)
        ->post(route('project-types.store'), [
            'name' => 'Support',
            'states' => [
                ['name' => 'New', 'category' => 'backlog', 'color' => '#9ca3af', 'isDefault' => true],
                ['name' => 'Resolved', 'category' => 'completed', 'color' => '#1d9e75', 'isDefault' => false],
            ],
        ])
        ->assertRedirect(route('project-types.index'));

    $type = ProjectType::query()->where('name', 'Support')->first();
    expect($type)->not->toBeNull()
        ->and($type->states)->toHaveCount(2)
        ->and($type->states->firstWhere('name', 'New')->category)->toBe(StatusCategory::Backlog)
        ->and($type->states->firstWhere('name', 'New')->is_default)->toBeTrue();
});

it('updates a type, adding, renaming and dropping lanes and reassigning orphaned issues', function () {
    [$org, $user] = organizationWith();
    $type = ProjectType::factory()->for($org)->create(['name' => 'Flow']);
    $keep = WorkflowState::factory()->for($type)->create(['name' => 'Todo', 'category' => StatusCategory::Backlog, 'position' => 0, 'is_default' => true]);
    $drop = WorkflowState::factory()->for($type)->create(['name' => 'Doing', 'category' => StatusCategory::Started, 'position' => 1]);

    $project = projectInOrganization($org, $user, ['key' => 'ABC', 'project_type_id' => $type->id]);
    $issue = Issue::factory()->for($project)->create(['workflow_state_id' => $drop->id]);

    $this->actingAs($user)
        ->patch(route('project-types.update', $type), [
            'name' => 'Flow',
            'states' => [
                ['id' => $keep->id, 'name' => 'Backlog', 'category' => 'backlog', 'color' => '#9ca3af', 'isDefault' => true],
                ['name' => 'Done', 'category' => 'completed', 'color' => '#1d9e75', 'isDefault' => false],
            ],
        ])
        ->assertRedirect();

    $type->refresh()->load('states');
    expect($type->states->pluck('name')->all())->toBe(['Backlog', 'Done'])
        ->and(WorkflowState::query()->whereKey($drop->id)->exists())->toBeFalse()
        // The dropped lane's issue moved to the surviving default lane.
        ->and($issue->fresh()->workflow_state_id)->toBe($keep->id);
});

it('will not delete a default or in-use type', function () {
    [$org, $user] = organizationWith();
    $default = ProjectType::factory()->for($org)->create(['is_default' => true]);
    $used = ProjectType::factory()->for($org)->create(['is_default' => false]);
    projectInOrganization($org, $user, ['key' => 'ABC', 'project_type_id' => $used->id]);

    $this->actingAs($user)->delete(route('project-types.destroy', $default));
    $this->actingAs($user)->delete(route('project-types.destroy', $used));

    expect(ProjectType::query()->whereKey($default->id)->exists())->toBeTrue()
        ->and(ProjectType::query()->whereKey($used->id)->exists())->toBeTrue();
});

it('deletes an unused non-default type', function () {
    [$org, $user] = organizationWith();
    $type = ProjectType::factory()->for($org)->create(['is_default' => false]);

    $this->actingAs($user)
        ->delete(route('project-types.destroy', $type))
        ->assertRedirect(route('project-types.index'));

    expect(ProjectType::query()->whereKey($type->id)->exists())->toBeFalse();
});

it('forbids a non-manager from creating a type', function () {
    [$org, $user] = organizationWith(OrganizationRole::Member);

    $this->actingAs($user)
        ->post(route('project-types.store'), [
            'name' => 'Nope',
            'states' => [['name' => 'A', 'category' => 'backlog', 'color' => '#9ca3af', 'isDefault' => true]],
        ])
        ->assertForbidden();

    expect(ProjectType::query()->where('name', 'Nope')->exists())->toBeFalse();
});

it('assigns the organization default type to a new project', function () {
    [$org, $user] = organizationWith();
    $default = ProjectType::factory()->for($org)->create(['is_default' => true]);

    $project = (new CreateProjectAction)->handle(['key' => 'NEW', 'name' => 'New'], $user, $org);

    expect($project->project_type_id)->toBe($default->id);
});
