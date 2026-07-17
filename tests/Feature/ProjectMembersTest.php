<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

it('lists members with their roles for a member of the project', function () {
    $owner = User::factory()->create(['name' => 'Olivia Owner']);
    $dev = User::factory()->create(['name' => 'Dave Dev']);
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($dev, $project, ProjectRole::Member);

    $this->actingAs($owner)
        ->get('/projects/THI/members')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Members')
            ->where('canManage', true)
            ->has('members', 2)
            ->where('members.0.name', 'Dave Dev')
            ->where('members.0.role', 'member')
            ->where('members.1.role', 'owner')
        );
});

it('marks a plain member as unable to manage', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($member, $project, ProjectRole::Member);

    $this->actingAs($member)
        ->get('/projects/THI/members')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));
});

it('forbids a non-member from viewing the members page', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);

    $this->actingAs($outsider)->get('/projects/THI/members')->assertForbidden();
});

it('lets an owner change a member role', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($member, $project, ProjectRole::Member);

    $this->actingAs($owner)
        ->patch("/projects/THI/members/{$member->id}", ['role' => 'admin'])
        ->assertRedirect();

    expect($project->roleFor($member))->toBe(ProjectRole::Admin);
});

it('lets an admin change another member role', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($admin, $project, ProjectRole::Admin);
    joinProjects($member, $project, ProjectRole::Member);

    $this->actingAs($admin)
        ->patch("/projects/THI/members/{$member->id}", ['role' => 'admin'])
        ->assertRedirect();

    expect($project->roleFor($member))->toBe(ProjectRole::Admin);
});

it('forbids a plain member from changing roles', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($member, $project, ProjectRole::Member);
    joinProjects($other, $project, ProjectRole::Member);

    $this->actingAs($member)
        ->patch("/projects/THI/members/{$other->id}", ['role' => 'admin'])
        ->assertForbidden();

    expect($project->roleFor($other))->toBe(ProjectRole::Member);
});

it('forbids changing the owner role', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($admin, $project, ProjectRole::Admin);

    $this->actingAs($admin)
        ->patch("/projects/THI/members/{$owner->id}", ['role' => 'member'])
        ->assertForbidden();

    expect($project->roleFor($owner))->toBe(ProjectRole::Owner);
});

it('rejects promoting a member to owner via the members endpoint', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($member, $project, ProjectRole::Member);

    $this->actingAs($owner)
        ->patch("/projects/THI/members/{$member->id}", ['role' => 'owner'])
        ->assertSessionHasErrors('role');

    expect($project->roleFor($member))->toBe(ProjectRole::Member);
});

it('lets an owner remove a member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($member, $project, ProjectRole::Member);

    $this->actingAs($owner)
        ->delete("/projects/THI/members/{$member->id}")
        ->assertRedirect();

    expect($project->hasMember($member))->toBeFalse();
});

it('forbids removing the owner', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($admin, $project, ProjectRole::Admin);

    $this->actingAs($admin)
        ->delete("/projects/THI/members/{$owner->id}")
        ->assertForbidden();

    expect($project->hasMember($owner))->toBeTrue();
});

it('returns 404 when acting on a user who is not a member', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);

    $this->actingAs($owner)
        ->delete("/projects/THI/members/{$stranger->id}")
        ->assertNotFound();
});
