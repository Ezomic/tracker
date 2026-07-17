<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Models\Label;
use App\Models\User;

it('only shows the current organization labels on the issues page', function () {
    [$org, $owner] = organizationWith();
    projectInOrganization($org, $owner, ['key' => 'THI']);
    Label::factory()->for($org)->create(['name' => 'mine']);
    Label::factory()->create(['name' => 'theirs']);

    $this->actingAs($owner)->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->has('labels', 1)
            ->where('labels.0.name', 'mine')
        );
});

it('offers a project organization labels to any collaborator', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    Label::factory()->for($org)->create(['name' => 'org-label']);

    $collaborator = User::factory()->create();
    joinProjects($collaborator, $project, ProjectRole::Member);

    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);

    $this->actingAs($collaborator)->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->has('labels', 1)
            ->where('labels.0.name', 'org-label')
        );
});

it('rejects attaching a label from a different organization', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);
    $foreign = Label::factory()->create(['name' => 'theirs']);

    $this->actingAs($owner)->patch("/issues/{$issue->identifier}", [
        'title' => $issue->title,
        'type' => 'feature',
        'priority' => 'none',
        'labels' => [$foreign->id],
    ])->assertSessionHasErrors('labels.0');

    expect($issue->fresh()->labels)->toBeEmpty();
});

it('deletes an organization labels with the organization', function () {
    [$org] = organizationWith();
    Label::factory()->for($org)->create();

    $org->delete();

    expect(Label::query()->count())->toBe(0);
});
