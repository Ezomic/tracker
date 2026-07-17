<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
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
    joinProjects($member, $project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($member)->get("/issues/{$issue->identifier}")->assertOk();
    $this->actingAs($member)->get('/THI/board')->assertOk();
});

it('forbids a plain member from editing project settings', function () {
    $member = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Original']);
    joinProjects($member, $project, ProjectLevel::Write);

    $this->actingAs($member)
        ->patch("/projects/{$project->id}", ['key' => 'THI', 'name' => 'Changed'])
        ->assertForbidden();

    expect($project->fresh()->name)->toBe('Original');
});

it('applies the level permission matrix', function () {
    $admin = User::factory()->create();
    $writer = User::factory()->create();
    $reader = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($admin, $project, ProjectLevel::Admin);
    joinProjects($writer, $project, ProjectLevel::Write);
    joinProjects($reader, $project, ProjectLevel::Read);

    expect($admin->can('view', $project))->toBeTrue()
        ->and($admin->can('createIssue', $project))->toBeTrue()
        ->and($admin->can('update', $project))->toBeTrue()
        ->and($admin->can('manageMembers', $project))->toBeTrue()
        ->and($admin->can('delete', $project))->toBeTrue();

    expect($writer->can('view', $project))->toBeTrue()
        ->and($writer->can('createIssue', $project))->toBeTrue()
        ->and($writer->can('update', $project))->toBeFalse()
        ->and($writer->can('manageMembers', $project))->toBeFalse()
        ->and($writer->can('delete', $project))->toBeFalse();

    expect($reader->can('view', $project))->toBeTrue()
        ->and($reader->can('createIssue', $project))->toBeFalse()
        ->and($reader->can('update', $project))->toBeFalse();
});

it('resolves effective level as the max of a direct grant and the org role', function () {
    [$org, $orgAdmin] = organizationWith(OrganizationRole::Admin);
    $project = Project::factory()->create(['key' => 'THI', 'organization_id' => $org->id]);

    // Org admin implies project admin even with only a read grant, or none.
    joinProjects($orgAdmin, $project, ProjectLevel::Read);
    expect($project->effectiveLevel($orgAdmin))->toBe(ProjectLevel::Admin);

    $plain = User::factory()->create();
    $org->members()->attach($plain->id, ['role' => OrganizationRole::Member->value]);
    joinProjects($plain, $project, ProjectLevel::Write);
    // Org member implies nothing, so their write grant stands.
    expect($project->effectiveLevel($plain))->toBe(ProjectLevel::Write);
});
