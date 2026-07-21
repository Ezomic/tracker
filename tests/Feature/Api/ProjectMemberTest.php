<?php

declare(strict_types=1);

use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\User;

function orgMemberFor(Project $project, ?string $role = 'member'): User
{
    $user = User::factory()->create();
    $project->organization?->members()->attach($user->id, ['role' => $role]);

    return $user;
}

it('lists project members', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'SHOP']);

    $this->actingAs($owner, 'sanctum')->getJson('/api/projects/SHOP/members')
        ->assertOk()
        ->assertJson([['id' => $owner->id, 'level' => 'admin']]);
});

it('adds an organization member to the project', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'SHOP']);
    $newcomer = orgMemberFor($project);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/projects/SHOP/members', ['user_id' => $newcomer->id, 'level' => 'write'])
        ->assertCreated();

    expect($project->members()->whereKey($newcomer->id)->exists())->toBeTrue();
});

it('404s when adding a user who is not in the organization', function () {
    [$org, $owner] = organizationWith();
    projectInOrganization($org, $owner, ['key' => 'SHOP']);
    $outsider = User::factory()->create();

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/projects/SHOP/members', ['user_id' => $outsider->id, 'level' => 'read'])
        ->assertNotFound();
});

it('rejects adding someone who is already a member', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'SHOP']);

    $this->actingAs($owner, 'sanctum')
        ->postJson('/api/projects/SHOP/members', ['user_id' => $owner->id, 'level' => 'admin'])
        ->assertUnprocessable();
});

it('updates and removes a member', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'SHOP']);
    $member = orgMemberFor($project);
    $project->members()->attach($member->id, ['level' => ProjectLevel::Read->value, 'is_favorite' => false]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson("/api/projects/SHOP/members/{$member->id}", ['level' => 'write', 'own_issues_only' => true])
        ->assertOk()
        ->assertJson(['level' => 'write', 'ownIssuesOnly' => true]);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/projects/SHOP/members/{$member->id}")
        ->assertNoContent();

    expect($project->members()->whereKey($member->id)->exists())->toBeFalse();
});

it('forbids a non-admin member from managing members', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'SHOP']);
    $writer = orgMemberFor($project);
    $project->members()->attach($writer->id, ['level' => ProjectLevel::Write->value, 'is_favorite' => false]);
    $target = orgMemberFor($project);

    $this->actingAs($writer, 'sanctum')
        ->postJson('/api/projects/SHOP/members', ['user_id' => $target->id, 'level' => 'read'])
        ->assertForbidden();
});

it('requires authentication', function () {
    [$org, $owner] = organizationWith();
    projectInOrganization($org, $owner, ['key' => 'SHOP']);

    $this->getJson('/api/projects/SHOP/members')->assertUnauthorized();
});
