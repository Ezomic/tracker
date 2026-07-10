<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Project;
use App\Models\User;

it('updates an issue status and stamps closed_at when done', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $response = $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}/status", [
        'status' => 'done',
    ]);

    $response->assertOk()->assertJson(['identifier' => $issue->identifier]);
    expect($issue->fresh()->status)->toBe(IssueStatus::Done)
        ->and($issue->fresh()->closed_at)->not->toBeNull();
});

it('clears closed_at when moving an issue out of done', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()])->save();

    $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}/status", [
        'status' => 'in_progress',
    ])->assertOk();

    expect($issue->fresh()->status)->toBe(IssueStatus::InProgress)
        ->and($issue->fresh()->closed_at)->toBeNull();
});

it('rejects an unknown status value', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}/status", [
        'status' => 'archived',
    ])->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('requires authentication', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $this->patchJson("/api/issues/{$issue->identifier}/status", ['status' => 'done'])
        ->assertUnauthorized();
});
