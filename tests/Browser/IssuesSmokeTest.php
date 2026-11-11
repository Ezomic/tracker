<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Project;

it('shows an issue without time tracking or billing controls', function () {
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Acme Software']);
    $user = member($project);
    $issue = (new CreateIssueAction)->handle($project, 'Rotate the deploy token', IssueType::Fix);

    $this->actingAs($user);

    visit("/issues/{$issue->identifier}")
        ->assertSee('THI-1')
        ->assertSee('Activity')
        ->assertDontSee('Invoiceable')
        ->assertDontSee('Add estimate')
        ->assertNoJavascriptErrors();
});

it('shows the issues list to a signed-in member', function () {
    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Acme Software']);
    $user = member($project);
    (new CreateIssueAction)->handle($project, 'Rotate the deploy token', IssueType::Fix);

    $this->actingAs($user);

    visit('/issues')
        ->assertSee('Rotate the deploy token')
        ->assertSee('THI-1')
        ->assertNoJavascriptErrors();
});
