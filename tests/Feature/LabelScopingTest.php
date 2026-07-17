<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;

it('never shows another tenant labels on the issues page', function () {
    $mine = Project::factory()->create(['key' => 'THI']);
    $owner = member($mine);
    Label::factory()->for($owner)->create(['name' => 'mine']);

    $stranger = User::factory()->create();
    Label::factory()->for($stranger)->create(['name' => 'theirs']);

    $this->actingAs($owner)->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->has('labels', 1)
            ->where('labels.0.name', 'mine')
        );
});

it('offers the project owner labels to a collaborator, not their own', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    Label::factory()->for($owner)->create(['name' => 'project-label']);

    $collaborator = User::factory()->create();
    joinProjects($collaborator, $project, ProjectRole::Member);
    Label::factory()->for($collaborator)->create(['name' => 'personal-label']);

    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);

    // A collaborator labels the issue from the project's set, so labels stay
    // consistent no matter who is filing.
    $this->actingAs($collaborator)->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->has('labels', 1)
            ->where('labels.0.name', 'project-label')
        );
});

it('rejects attaching a label from outside the project set', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $owner = member($project);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);
    $foreign = Label::factory()->for(User::factory()->create())->create(['name' => 'theirs']);

    $this->actingAs($owner)->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'labels' => [$foreign->id],
    ])->assertSessionHasErrors('labels.0');

    expect($issue->fresh()->labels)->toBeEmpty();
});

it('rejects a template label from outside the project set', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $foreign = Label::factory()->for(User::factory()->create())->create();

    $this->actingAs(member($project))
        ->post('/projects/THI/templates', [
            'name' => 'Bug report',
            'labels' => [$foreign->id],
        ])
        ->assertSessionHasErrors('labels.0');
});

it('deletes a users labels with the user', function () {
    $user = User::factory()->create();
    Label::factory()->for($user)->create();

    $user->delete();

    expect(Label::query()->count())->toBe(0);
});
