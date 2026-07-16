<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;

function createFilterableIssue(
    Project $team,
    string $title,
    IssueType $type = IssueType::Feature,
    IssuePriority $priority = IssuePriority::None,
    IssueStatus $status = IssueStatus::Backlog,
): Issue {
    $issue = (new CreateIssueAction)->handle($team, $title, $type);
    $issue->forceFill(['priority' => $priority, 'status' => $status])->save();

    return $issue;
}

it('filters issues by title search', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    createFilterableIssue($team, 'Fix login redirect bug');
    createFilterableIssue($team, 'Add quiz question pools');

    $this->actingAs(User::factory()->create())
        ->get('/issues?search=login')
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'Fix login redirect bug')
        );
});

it('filters issues by team', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    $billr = Project::factory()->create(['key' => 'BILLR']);
    createFilterableIssue($thi, 'THI issue');
    createFilterableIssue($billr, 'BILLR issue');

    $this->actingAs(User::factory()->create())
        ->get("/issues?project_id={$billr->id}")
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'BILLR issue')
        );
});

it('filters issues by status', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    createFilterableIssue($team, 'Backlog issue', status: IssueStatus::Backlog);
    createFilterableIssue($team, 'Done issue', status: IssueStatus::Done);

    $this->actingAs(User::factory()->create())
        ->get('/issues?status=done')
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'Done issue')
        );
});

it('filters issues by type', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    createFilterableIssue($team, 'A feature', type: IssueType::Feature);
    createFilterableIssue($team, 'A fix', type: IssueType::Fix);

    $this->actingAs(User::factory()->create())
        ->get('/issues?type=fix')
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'A fix')
        );
});

it('filters issues by priority', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    createFilterableIssue($team, 'Urgent issue', priority: IssuePriority::Urgent);
    createFilterableIssue($team, 'No priority issue', priority: IssuePriority::None);

    $this->actingAs(User::factory()->create())
        ->get('/issues?priority=urgent')
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'Urgent issue')
        );
});

it('filters issues by label', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $bug = Label::factory()->create(['name' => 'bug']);
    $labeled = createFilterableIssue($team, 'Labeled issue');
    $labeled->labels()->attach($bug);
    createFilterableIssue($team, 'Unlabeled issue');

    $this->actingAs(User::factory()->create())
        ->get("/issues?label_id={$bug->id}")
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'Labeled issue')
        );
});

it('combines multiple filters', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    createFilterableIssue($team, 'Urgent fix', type: IssueType::Fix, priority: IssuePriority::Urgent);
    createFilterableIssue($team, 'Urgent feature', type: IssueType::Feature, priority: IssuePriority::Urgent);

    $this->actingAs(User::factory()->create())
        ->get('/issues?type=fix&priority=urgent')
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'Urgent fix')
        );
});

it('returns all issues and echoes back empty filters when none are given', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    createFilterableIssue($team, 'An issue');

    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('filters.search', null)
            ->where('filters.project_id', null)
        );
});

it('rejects an invalid filter value', function () {
    $this->actingAs(User::factory()->create())
        ->get('/issues?status=not-a-status')
        ->assertSessionHasErrors('status');
});
