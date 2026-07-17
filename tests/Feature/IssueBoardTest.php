<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Project;

it('renders the board with all issues regardless of status', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::InProgress])->save();

    $this->actingAs(member($team))
        ->get('/issues/board')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('issues/Board')
            ->where('issues.0.identifier', 'THI-1')
            ->where('issues.0.status', 'in_progress')
        );
});

it('hides archived issues by default and shows them when asked', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    (new CreateIssueAction)->handle($team, 'Active', IssueType::Feature);
    $archived = (new CreateIssueAction)->handle($team, 'Old', IssueType::Feature);
    $archived->forceFill(['archived_at' => now()])->save();
    $user = member($team);

    $this->actingAs($user)->get('/issues/board')
        ->assertInertia(fn ($page) => $page
            ->where('showArchived', false)
            ->has('issues', 1)
            ->where('issues.0.identifier', 'THI-1')
        );

    $this->actingAs($user)->get('/issues/board?archived=1')
        ->assertInertia(fn ($page) => $page
            ->where('showArchived', true)
            ->has('issues', 2)
        );
});

it('updates an issue status via drag and drop', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(member($team))
        ->patch("/issues/{$issue->identifier}/status", ['status' => 'in_review'])
        ->assertRedirect();

    expect($issue->fresh()->status)->toBe(IssueStatus::InReview);
});

it('sets closed_at when moved to done and clears it when moved away', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $user = member($team);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}/status", ['status' => 'done']);
    expect($issue->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($user)->patch("/issues/{$issue->identifier}/status", ['status' => 'backlog']);
    expect($issue->fresh()->closed_at)->toBeNull();
});

it('rejects an invalid status', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(member($team))
        ->patch("/issues/{$issue->identifier}/status", ['status' => 'archived'])
        ->assertSessionHasErrors('status');
});
