<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Team;
use App\Models\User;

it('renders the board with all issues regardless of status', function () {
    $team = Team::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::InProgress])->save();

    $this->actingAs(User::factory()->create())
        ->get('/issues/board')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('issues/Board')
            ->where('issues.0.identifier', 'THI-1')
            ->where('issues.0.status', 'in_progress')
        );
});

it('updates an issue status via drag and drop', function () {
    $team = Team::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->patch("/issues/{$issue->identifier}/status", ['status' => 'in_review'])
        ->assertRedirect();

    expect($issue->fresh()->status)->toBe(IssueStatus::InReview);
});

it('sets closed_at when moved to done and clears it when moved away', function () {
    $team = Team::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $user = User::factory()->create();

    $this->actingAs($user)->patch("/issues/{$issue->identifier}/status", ['status' => 'done']);
    expect($issue->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($user)->patch("/issues/{$issue->identifier}/status", ['status' => 'backlog']);
    expect($issue->fresh()->closed_at)->toBeNull();
});

it('rejects an invalid status', function () {
    $team = Team::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->patch("/issues/{$issue->identifier}/status", ['status' => 'archived'])
        ->assertSessionHasErrors('status');
});
