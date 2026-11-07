<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Project;

it('hides the Billr billing controls on an issue when Billr is not configured', function () {
    config()->set('services.billr.token', null);

    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Acme Software']);
    $user = member($project);
    $issue = (new CreateIssueAction)->handle($project, 'Rotate the deploy token', IssueType::Fix);

    $this->actingAs($user);

    visit("/issues/{$issue->identifier}")
        ->assertSee('Rotate the deploy token')
        ->assertDontSee('Invoiceable')
        ->assertNoJavascriptErrors();
});

it('shows the Billr billing controls on an issue when Billr is configured', function () {
    configureBillr();

    $project = Project::factory()->create(['key' => 'THI', 'name' => 'Acme Software']);
    $user = member($project);
    $issue = (new CreateIssueAction)->handle($project, 'Rotate the deploy token', IssueType::Fix);

    $this->actingAs($user);

    visit("/issues/{$issue->identifier}")
        ->assertSee('Invoiceable')
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
