<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Project;
use App\Models\User;

it('scopes the tickets view to the project in the url', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    $billr = Project::factory()->create(['key' => 'BILLR']);
    (new CreateIssueAction)->handle($thi, 'THI issue', IssueType::Feature);
    (new CreateIssueAction)->handle($billr, 'BILLR issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->get('/THI/tickets')
        ->assertInertia(fn ($page) => $page
            ->component('issues/Index')
            ->has('issues', 1)
            ->where('issues.0.identifier', 'THI-1')
            ->where('filters.team_id', $thi->id)
        );
});

it('scopes the board view to the project in the url', function () {
    $thi = Project::factory()->create(['key' => 'THI']);
    $billr = Project::factory()->create(['key' => 'BILLR']);
    (new CreateIssueAction)->handle($thi, 'THI issue', IssueType::Feature);
    (new CreateIssueAction)->handle($billr, 'BILLR issue', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->get('/THI/board')
        ->assertInertia(fn ($page) => $page
            ->component('issues/Board')
            ->has('issues', 1)
            ->where('issues.0.identifier', 'THI-1')
        );
});

it('returns 404 for an unknown project key', function () {
    $this->actingAs(User::factory()->create())
        ->get('/NOPE/tickets')
        ->assertNotFound();
});

it('shares projects to authenticated pages', function () {
    Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software', 'color' => '#d85a30']);

    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertInertia(fn ($page) => $page
            ->has('sidebarProjects', 1)
            ->where('sidebarProjects.0.key', 'THI')
            ->where('sidebarProjects.0.color', '#d85a30')
        );
});
