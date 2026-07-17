<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\User;

it('lists organization members with their roles to a manager', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $member = User::factory()->create(['name' => 'Aaron']);
    $org->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($owner)->get('/settings/members')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Members')
            ->has('members', 2)
            ->where('members.0.name', 'Aaron')
            ->where('members.0.role', 'member')
        );
});

it('lets a manager change a member role', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $member = User::factory()->create();
    $org->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($owner)
        ->patch("/settings/members/{$member->id}", ['role' => 'admin'])
        ->assertRedirect();

    expect($org->roleFor($member))->toBe(OrganizationRole::Admin);
});

it('will not let a manager change the owner or themselves', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $admin = User::factory()->create();
    $org->members()->attach($admin->id, ['role' => OrganizationRole::Admin->value]);

    // The owner is untouchable.
    $this->actingAs($admin)
        ->patch("/settings/members/{$owner->id}", ['role' => 'member'])
        ->assertForbidden();

    // And you can't manage your own membership here.
    $this->actingAs($admin)
        ->patch("/settings/members/{$admin->id}", ['role' => 'member'])
        ->assertForbidden();
});

it('removes a member and strips their project grants', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $project = Project::factory()->create(['key' => 'THI', 'organization_id' => $org->id]);
    $member = User::factory()->create();
    $org->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);
    joinProjects($member, $project, ProjectLevel::Write);

    $this->actingAs($owner)
        ->delete("/settings/members/{$member->id}")
        ->assertRedirect();

    expect($org->hasMember($member))->toBeFalse()
        ->and($project->grantFor($member))->toBeNull();
});

it('rejects an unknown role', function () {
    [$org, $owner] = organizationWith(OrganizationRole::Owner);
    $member = User::factory()->create();
    $org->members()->attach($member->id, ['role' => OrganizationRole::Member->value]);

    $this->actingAs($owner)
        ->patch("/settings/members/{$member->id}", ['role' => 'owner'])
        ->assertSessionHasErrors('role');
});
