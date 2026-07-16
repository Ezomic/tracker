<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

it('hides a project and its issues from non-members', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project);
    $issue = (new CreateIssueAction)->handle($project, 'Secret', IssueType::Feature);

    $this->actingAs($outsider)->get('/projects')
        ->assertInertia(fn ($page) => $page->has('projects', 0));

    $this->actingAs($outsider)->get('/issues')
        ->assertInertia(fn ($page) => $page->has('issues', 0)->has('projects', 0));

    $this->actingAs($outsider)->get('/THI/board')->assertForbidden();
    $this->actingAs($outsider)->get("/issues/{$issue->identifier}")->assertForbidden();
});

it('lets a member view a project they belong to', function () {
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($member, $project, ProjectRole::Member);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($member)->get("/issues/{$issue->identifier}")->assertOk();
    $this->actingAs($member)->get('/THI/board')->assertOk();
});

it('forbids a plain member from editing project settings', function () {
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Original']);
    joinProjects($member, $project, ProjectRole::Member);

    $this->actingAs($member)
        ->patch("/projects/{$project->id}", ['key' => 'THI', 'name' => 'Changed'])
        ->assertForbidden();

    expect($project->fresh()->name)->toBe('Original');
});

it('lets an admin edit project settings but not delete the project', function () {
    $admin = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($admin, $project, ProjectRole::Admin);

    $this->actingAs($admin)
        ->patch("/projects/{$project->id}", ['key' => 'THI', 'name' => 'Renamed'])
        ->assertRedirect(route('projects.index'));

    expect($project->fresh()->name)->toBe('Renamed');
    expect($admin->can('delete', $project))->toBeFalse();
});

it('applies the role permission matrix', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($admin, $project, ProjectRole::Admin);
    joinProjects($member, $project, ProjectRole::Member);

    expect($owner->can('update', $project))->toBeTrue()
        ->and($owner->can('manageMembers', $project))->toBeTrue()
        ->and($owner->can('delete', $project))->toBeTrue();

    expect($admin->can('update', $project))->toBeTrue()
        ->and($admin->can('manageMembers', $project))->toBeTrue()
        ->and($admin->can('delete', $project))->toBeFalse();

    expect($member->can('update', $project))->toBeFalse()
        ->and($member->can('manageMembers', $project))->toBeFalse()
        ->and($member->can('delete', $project))->toBeFalse();
});

it('resolves the owner of a project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($owner, $project, ProjectRole::Owner);
    joinProjects($member, $project, ProjectRole::Member);

    expect($project->owner()->is($owner))->toBeTrue();
});
