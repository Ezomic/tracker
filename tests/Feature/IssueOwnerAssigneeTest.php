<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

it('stamps the creator as owner when filing from the web', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $creator = member($project);

    $this->actingAs($creator)->post('/issues', [
        'project_id' => $project->id,
        'title' => 'A new issue',
        'type' => 'feature',
    ])->assertRedirect('/issues/THI-1');

    $issue = Issue::query()->where('identifier', 'THI-1')->firstOrFail();
    expect($issue->owner_id)->toBe($creator->id)
        ->and($issue->assignee_id)->toBeNull();
});

it('stamps the token user as owner when filing from the api', function () {
    $project = Project::factory()->create(['key' => 'THI', 'next_number' => 0]);
    $creator = member($project);

    $this->actingAs($creator, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'From the api',
        'type' => 'feature',
    ])->assertCreated()->assertJson(['owner' => $creator->email, 'assignee' => null]);

    expect(Issue::query()->firstOrFail()->owner_id)->toBe($creator->id);
});

it('assigns via the api by member email', function () {
    $project = Project::factory()->create(['key' => 'THI', 'next_number' => 0]);
    $creator = member($project);
    $dev = User::factory()->create(['email' => 'dev@example.com']);
    joinProjects($dev, $project, ProjectRole::Member);

    $this->actingAs($creator, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Assigned issue',
        'type' => 'feature',
        'assignee' => 'Dev@Example.com',
    ])->assertCreated()->assertJson(['assignee' => 'dev@example.com']);

    expect(Issue::query()->firstOrFail()->assignee_id)->toBe($dev->id);
});

it('rejects an api assignee who is not a project member', function () {
    $project = Project::factory()->create(['key' => 'THI', 'next_number' => 0]);
    $creator = member($project);
    User::factory()->create(['email' => 'outsider@example.com']);

    $this->actingAs($creator, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Nope',
        'type' => 'feature',
        'assignee' => 'outsider@example.com',
    ])->assertUnprocessable()->assertJsonValidationErrors('assignee');
});

it('rejects an api assignee who has no account', function () {
    $project = Project::factory()->create(['key' => 'THI', 'next_number' => 0]);

    $this->actingAs(member($project), 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Nope',
        'type' => 'feature',
        'assignee' => 'ghost@example.com',
    ])->assertUnprocessable()->assertJsonValidationErrors('assignee');
});

it('assigns a member from the ticket page', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    $dev = User::factory()->create();
    joinProjects($dev, $project, ProjectRole::Member);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature, owner: $owner);

    $this->actingAs($owner)->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'assignee_id' => $dev->id,
    ])->assertRedirect("/issues/{$issue->identifier}");

    expect($issue->fresh()->assignee_id)->toBe($dev->id);
});

it('clears the assignee when submitted empty', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature, owner: $owner, assignee: $owner);

    $this->actingAs($owner)->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'assignee_id' => '',
    ])->assertRedirect("/issues/{$issue->identifier}");

    expect($issue->fresh()->assignee_id)->toBeNull();
});

it('rejects assigning someone who is not a member of the project', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    $outsider = User::factory()->create();
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature, owner: $owner);

    $this->actingAs($owner)->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'assignee_id' => $outsider->id,
    ])->assertSessionHasErrors('assignee_id');

    expect($issue->fresh()->assignee_id)->toBeNull();
});

it('does not let the owner be reassigned through the update form', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    $other = User::factory()->create();
    joinProjects($other, $project, ProjectRole::Member);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature, owner: $owner);

    $this->actingAs($owner)->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'owner_id' => $other->id,
    ]);

    expect($issue->fresh()->owner_id)->toBe($owner->id);
});

it('exposes owner, assignee and project members on the ticket page', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature, owner: $owner, assignee: $owner);

    $this->actingAs($owner)->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->where('issue.owner.name', $owner->name)
            ->where('issue.assignee.id', $owner->id)
            ->has('members', 1)
            ->where('members.0.id', $owner->id)
        );
});
