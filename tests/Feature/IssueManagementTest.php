<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

it('renders the issues index with existing issues and teams', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('issues/Index')
            ->where('issues.0.identifier', 'THI-1')
            ->where('teams.0.key', 'THI')
        );
});

it('creates an issue from the web form and redirects to its detail page', function () {
    $team = Project::factory()->create(['key' => 'THI']);

    $response = $this->actingAs(User::factory()->create())
        ->post('/issues', [
            'team_id' => $team->id,
            'title' => 'Add quiz question pools',
            'type' => 'feature',
            'description' => 'Randomize quiz questions on replay.',
        ]);

    $response->assertRedirect('/issues/THI-1');
    expect(Issue::query()->where('identifier', 'THI-1')->exists())->toBeTrue();
});

it('returns validation errors for an unknown team, blank title, or invalid type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/issues', [
        'team_id' => 999,
        'title' => '',
        'type' => 'chore',
    ])->assertSessionHasErrors(['team_id', 'title', 'type']);
});

it('renders the issue detail page', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->get("/issues/{$issue->identifier}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('issues/Show')
            ->where('issue.identifier', 'THI-1')
            ->where('issue.branchName', $issue->branch_name)
        );
});

it('updates an issue title, type, priority, and description', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'Original title', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->patch("/issues/{$issue->identifier}", [
            'title' => 'Updated title',
            'type' => 'fix',
            'priority' => 'high',
            'description' => 'Updated description.',
        ])
        ->assertRedirect("/issues/{$issue->identifier}");

    expect($issue->fresh())
        ->title->toBe('Updated title')
        ->type->toBe(IssueType::Fix)
        ->priority->toBe(IssuePriority::High)
        ->description->toBe('Updated description.')
        ->branch_name->toBe($issue->branch_name);
});

it('rejects an invalid priority', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'Original title', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->patch("/issues/{$issue->identifier}", [
            'title' => 'Updated title',
            'type' => 'feature',
            'priority' => 'super-urgent',
        ])
        ->assertSessionHasErrors('priority');
});

it('defaults a newly created issue to no priority', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    expect($issue->priority)->toBe(IssuePriority::None);
});
