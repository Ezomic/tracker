<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\User;

it('lists members with their levels for anyone with access', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $admin = member($project, ProjectLevel::Admin);
    $dev = User::factory()->create(['name' => 'Dave Dev']);
    joinProjects($dev, $project, ProjectLevel::Write);

    $this->actingAs($admin)
        ->get('/projects/THI/members')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Members')
            ->where('canManage', true)
            ->has('members', 2)
            ->where('members.0.name', 'Dave Dev')
            ->where('members.0.level', 'write')
            ->where('members.1.level', 'admin')
        );
});

it('marks someone with only read as unable to manage', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    member($project, ProjectLevel::Admin);
    $reader = User::factory()->create();
    joinProjects($reader, $project, ProjectLevel::Read);

    $this->actingAs($reader)
        ->get('/projects/THI/members')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));
});

it('forbids a non-member from viewing the members page', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    member($project, ProjectLevel::Admin);

    $this->actingAs(User::factory()->create())->get('/projects/THI/members')->assertForbidden();
});

it('lets an admin change a member level', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $admin = member($project, ProjectLevel::Admin);
    $dev = User::factory()->create();
    joinProjects($dev, $project, ProjectLevel::Write);

    $this->actingAs($admin)
        ->patch("/projects/THI/members/{$dev->id}", ['level' => 'admin'])
        ->assertRedirect();

    expect($project->grantFor($dev))->toBe(ProjectLevel::Admin);
});

it('forbids a write member from changing levels', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    member($project, ProjectLevel::Admin);
    $dev = User::factory()->create();
    joinProjects($dev, $project, ProjectLevel::Write);
    $other = User::factory()->create();
    joinProjects($other, $project, ProjectLevel::Write);

    $this->actingAs($dev)
        ->patch("/projects/THI/members/{$other->id}", ['level' => 'admin'])
        ->assertForbidden();

    expect($project->grantFor($other))->toBe(ProjectLevel::Write);
});

it('rejects an unknown level', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $admin = member($project, ProjectLevel::Admin);
    $dev = User::factory()->create();
    joinProjects($dev, $project, ProjectLevel::Write);

    $this->actingAs($admin)
        ->patch("/projects/THI/members/{$dev->id}", ['level' => 'owner'])
        ->assertSessionHasErrors('level');

    expect($project->grantFor($dev))->toBe(ProjectLevel::Write);
});

it('lets an admin remove a member', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $admin = member($project, ProjectLevel::Admin);
    $dev = User::factory()->create();
    joinProjects($dev, $project, ProjectLevel::Write);

    $this->actingAs($admin)
        ->delete("/projects/THI/members/{$dev->id}")
        ->assertRedirect();

    expect($project->grantFor($dev))->toBeNull();
});

it('404s when acting on a user who has no grant on the project', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $admin = member($project, ProjectLevel::Admin);
    $stranger = User::factory()->create();

    $this->actingAs($admin)
        ->delete("/projects/THI/members/{$stranger->id}")
        ->assertNotFound();
});

it('offers assignable organization members to a manager', function () {
    [$org, $owner] = organizationWith();
    $project = Project::factory()->create(['key' => 'THI', 'organization_id' => $org->id]);
    joinProjects($owner, $project, ProjectLevel::Admin);
    $colleague = User::factory()->create(['name' => 'Colleague']);
    $org->members()->attach($colleague->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($owner)->get('/projects/THI/members')
        ->assertInertia(fn ($page) => $page
            ->has('assignable', 1)
            ->where('assignable.0.name', 'Colleague')
        );
});

it('lets a manager add an existing organization member to the project', function () {
    [$org, $owner] = organizationWith();
    $project = Project::factory()->create(['key' => 'THI', 'organization_id' => $org->id]);
    joinProjects($owner, $project, ProjectLevel::Admin);
    $colleague = User::factory()->create();
    $org->members()->attach($colleague->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($owner)
        ->post('/projects/THI/members', ['user_id' => $colleague->id, 'level' => 'write'])
        ->assertRedirect();

    expect($project->grantFor($colleague))->toBe(ProjectLevel::Write);
});

it('refuses to add someone who is not in the organization', function () {
    [$org, $owner] = organizationWith();
    $project = Project::factory()->create(['key' => 'THI', 'organization_id' => $org->id]);
    joinProjects($owner, $project, ProjectLevel::Admin);
    $stranger = User::factory()->create();

    $this->actingAs($owner)
        ->post('/projects/THI/members', ['user_id' => $stranger->id, 'level' => 'write'])
        ->assertNotFound();

    expect($project->grantFor($stranger))->toBeNull();
});

it('grants an organization owner admin without a direct project grant', function () {
    [$org, $orgOwner] = organizationWith();
    $project = Project::factory()->create(['key' => 'THI', 'organization_id' => $org->id]);

    // No project_user row for the org owner, yet they can manage it.
    expect($project->grantFor($orgOwner))->toBeNull()
        ->and($project->effectiveLevel($orgOwner))->toBe(ProjectLevel::Admin);

    $this->actingAs($orgOwner)->get('/projects/THI/members')
        ->assertInertia(fn ($page) => $page->where('canManage', true));
});
