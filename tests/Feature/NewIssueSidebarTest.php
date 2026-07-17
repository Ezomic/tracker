<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Project;

it('shares every project the user belongs to for the new-issue modal', function () {
    $thi = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen']);
    $cms = Project::factory()->create(['key' => 'CMS', 'name' => 'Portfolio']);
    Project::factory()->create(['key' => 'NOPE']);
    $user = member([$thi, $cms]);

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('newIssueProjects', 2)
            ->where('newIssueProjects.0.key', 'CMS')
            ->where('newIssueProjects.1.key', 'THI')
        );
});

it('shares favorited and unfavorited projects alike to the modal', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    $cms = Project::factory()->create(['key' => 'CMS']);
    $user = member($thi);
    $cms->members()->attach($user->id, ['role' => 'owner', 'is_favorite' => false]);

    // The sidebar nav only lists favorites, but you can file against any of them.
    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('sidebarProjects', 1)
            ->has('newIssueProjects', 2)
        );
});

it('has no current project on a global page', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    $this->actingAs(member($project))->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('currentProjectId', null));
});

it('resolves the current project from a project-scoped board', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    $this->actingAs(member($project))->get('/THI/board')
        ->assertInertia(fn ($page) => $page->where('currentProjectId', $project->id));
});

it('resolves the current project from a project-scoped ticket list', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    $this->actingAs(member($project))->get('/THI/tickets')
        ->assertInertia(fn ($page) => $page->where('currentProjectId', $project->id));
});

it('resolves the current project from an issue detail page', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);

    $this->actingAs(member($project))->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page->where('currentProjectId', $project->id));
});

it('resolves the current project on the members page', function () {
    $project = Project::factory()->create(['key' => 'THI']);

    $this->actingAs(member($project))->get('/projects/THI/members')
        ->assertInertia(fn ($page) => $page->where('currentProjectId', $project->id));
});
