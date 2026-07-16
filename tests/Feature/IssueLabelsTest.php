<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Label;
use App\Models\Project;

it('attaches labels to an issue on update', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $bug = Label::factory()->create(['name' => 'bug']);
    $urgent = Label::factory()->create(['name' => 'urgent']);

    $this->actingAs(member($team))
        ->patch("/issues/{$issue->identifier}", [
            'title' => $issue->title,
            'type' => 'feature',
            'priority' => 'none',
            'labels' => [$bug->id, $urgent->id],
        ])
        ->assertRedirect("/issues/{$issue->identifier}");

    expect($issue->fresh()->labels->pluck('id')->sort()->values()->all())
        ->toBe([$bug->id, $urgent->id]);
});

it('removes labels from an issue when omitted on update', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->labels()->attach(Label::factory()->create());

    $this->actingAs(member($team))
        ->patch("/issues/{$issue->identifier}", [
            'title' => $issue->title,
            'type' => 'feature',
            'priority' => 'none',
        ])
        ->assertRedirect("/issues/{$issue->identifier}");

    expect($issue->fresh()->labels)->toBeEmpty();
});

it('rejects an unknown label id', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(member($team))
        ->patch("/issues/{$issue->identifier}", [
            'title' => $issue->title,
            'type' => 'feature',
            'priority' => 'none',
            'labels' => [999],
        ])
        ->assertSessionHasErrors('labels.0');
});

it('serializes labels on the issue list, board, and detail pages', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->labels()->attach(Label::factory()->create(['name' => 'bug', 'color' => 'red']));

    $user = member($team);

    $this->actingAs($user)->get('/issues')
        ->assertInertia(fn ($page) => $page->where('issues.0.labels.0.name', 'bug'));

    $this->actingAs($user)->get('/issues/board')
        ->assertInertia(fn ($page) => $page->where('issues.0.labels.0.name', 'bug'));

    $this->actingAs($user)->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->where('issue.labels.0.name', 'bug')
            ->where('issue.labels.0.color', 'red')
        );
});
