<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    [$this->org, $this->owner] = organizationWith(OrganizationRole::Owner);
    $this->project = Project::factory()->create(['key' => 'THI', 'organization_id' => $this->org->id]);

    $this->restricted = User::factory()->create();
    $this->org->members()->attach($this->restricted->id, ['role' => OrganizationRole::Member->value]);
    joinProjects($this->restricted, $this->project, ProjectLevel::Write);
    $this->project->members()->updateExistingPivot($this->restricted->id, ['own_issues_only' => true]);

    $this->other = User::factory()->create();
    $this->org->members()->attach($this->other->id, ['role' => OrganizationRole::Member->value]);
    joinProjects($this->other, $this->project, ProjectLevel::Write);

    $create = new CreateIssueAction;
    $this->mine = $create->handle($this->project, 'Mine', IssueType::Feature, owner: $this->restricted);
    $this->assignedToMe = $create->handle($this->project, 'Assigned', IssueType::Feature, owner: $this->other, assignee: $this->restricted);
    $this->notMine = $create->handle($this->project, 'Theirs', IssueType::Feature, owner: $this->other);
});

it('shows a restricted member only issues they report or are assigned', function () {
    $this->actingAs($this->restricted)->get('/THI/board')
        ->assertInertia(fn ($page) => $page->has('issues', 2));

    $this->actingAs($this->restricted)->get('/issues')
        ->assertInertia(fn ($page) => $page->has('issues', 2));
});

it('lets a restricted member open issues they own or are assigned', function () {
    $this->actingAs($this->restricted)->get("/issues/{$this->mine->identifier}")->assertOk();
    $this->actingAs($this->restricted)->get("/issues/{$this->assignedToMe->identifier}")->assertOk();
});

it('forbids a restricted member from opening an issue that is not theirs', function () {
    $this->actingAs($this->restricted)->get("/issues/{$this->notMine->identifier}")->assertForbidden();

    expect($this->restricted->can('update', $this->notMine))->toBeFalse()
        ->and($this->restricted->can('update', $this->mine))->toBeTrue()
        ->and($this->restricted->can('update', $this->assignedToMe))->toBeTrue();
});

it('never restricts an org admin even when the flag is set on their grant', function () {
    $admin = User::factory()->create();
    $this->org->members()->attach($admin->id, ['role' => OrganizationRole::Admin->value]);
    joinProjects($admin, $this->project, ProjectLevel::Read);
    $this->project->members()->updateExistingPivot($admin->id, ['own_issues_only' => true]);

    $this->actingAs($admin)->get('/THI/board')
        ->assertInertia(fn ($page) => $page->has('issues', 3));

    $this->actingAs($admin)->get("/issues/{$this->notMine->identifier}")->assertOk();
});

it('persists the own_issues_only flag from the members endpoint', function () {
    $this->actingAs($this->owner)
        ->patch("/projects/THI/members/{$this->other->id}", [
            'level' => 'write',
            'own_issues_only' => true,
        ])
        ->assertRedirect();

    $pivot = $this->project->members()->find($this->other->id)?->getAttribute('pivot');

    expect((bool) $pivot?->getAttribute('own_issues_only'))->toBeTrue();
});
