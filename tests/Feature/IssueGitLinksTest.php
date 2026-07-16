<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Project;

it('derives branch and commit links from the pull request url', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = Issue::factory()->for($project)->create([
        'branch_name' => 'feature/THI-1-example',
        'github_pr_url' => 'https://github.com/Ezomic/tracker/pull/42',
    ]);

    $this->actingAs(member($project))
        ->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->component('issues/Show')
            ->where('issue.branchUrl', 'https://github.com/Ezomic/tracker/tree/feature/THI-1-example')
            ->where('issue.commitsUrl', 'https://github.com/Ezomic/tracker/commits/feature/THI-1-example')
            ->where('issue.githubPrUrl', 'https://github.com/Ezomic/tracker/pull/42')
        );
});

it('falls back to the project github_repos when there is no pull request', function () {
    $project = Project::factory()->create([
        'key' => 'THI',
        'github_repos' => ['Ezomic/tracker'],
    ]);
    $issue = Issue::factory()->for($project)->create([
        'branch_name' => 'feature/THI-2-example',
        'github_pr_url' => null,
    ]);

    $this->actingAs(member($project))
        ->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->where('issue.branchUrl', 'https://github.com/Ezomic/tracker/tree/feature/THI-2-example')
            ->where('issue.commitsUrl', 'https://github.com/Ezomic/tracker/commits/feature/THI-2-example')
        );
});

it('leaves links null when no repo can be resolved', function () {
    $project = Project::factory()->create(['key' => 'THI', 'github_repos' => null]);
    $issue = Issue::factory()->for($project)->create([
        'branch_name' => 'feature/THI-3-example',
        'github_pr_url' => null,
    ]);

    $this->actingAs(member($project))
        ->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->where('issue.branchUrl', null)
            ->where('issue.commitsUrl', null)
        );
});
