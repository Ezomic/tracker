<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Models\Project;

it('finds issues by title', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    (new CreateIssueAction)->handle($project, 'Rate limit the public API', IssueType::Feature);
    (new CreateIssueAction)->handle($project, 'Something unrelated', IssueType::Feature);

    $response = $this->actingAs($user)->getJson('/issues/search?q=rate limit');

    $response->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.title', 'Rate limit the public API')
        ->assertJsonPath('0.projectKey', 'THI');
});

it('finds an issue by its identifier', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Anything', IssueType::Feature);

    $this->actingAs($user)->getJson("/issues/search?q={$issue->identifier}")
        ->assertOk()
        ->assertJsonPath('0.identifier', $issue->identifier);
});

it('finds issues by description', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Title', IssueType::Feature);
    $issue->forceFill(['description' => 'mentions a webhook secret'])->save();

    $this->actingAs($user)->getJson('/issues/search?q=webhook secret')
        ->assertOk()
        ->assertJsonPath('0.identifier', $issue->identifier);
});

it('returns an empty list for a blank query', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    (new CreateIssueAction)->handle($project, 'Visible', IssueType::Feature);

    $this->actingAs($user)->getJson('/issues/search?q=')
        ->assertOk()
        ->assertExactJson([]);
});

it('does not return issues the user cannot see', function () {
    $mine = Project::factory()->create(['key' => 'MINE']);
    $user = member($mine, ProjectLevel::Write);
    $other = Project::factory()->create(['key' => 'HID']);

    (new CreateIssueAction)->handle($mine, 'Shared keyword alpha', IssueType::Feature);
    (new CreateIssueAction)->handle($other, 'Shared keyword beta', IssueType::Feature);

    $this->actingAs($user)->getJson('/issues/search?q=Shared keyword')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.projectKey', 'MINE');
});
