<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Project;
use App\Models\User;

it('archives an issue and stamps archived_at', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/issues/{$issue->identifier}");

    $response->assertOk()->assertJson(['identifier' => $issue->identifier]);
    expect($issue->fresh()->archived_at)->not->toBeNull();
});

it('drops an archived issue from the issues list', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/issues/{$issue->identifier}")->assertOk();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/issues')
        ->assertOk()
        ->assertJsonMissing(['identifier' => $issue->identifier]);
});

it('is idempotent and preserves the original archived_at', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);
    $issue->forceFill(['archived_at' => now()->subDay()])->save();
    $firstArchivedAt = $issue->fresh()->archived_at;

    $this->actingAs($user, 'sanctum')->deleteJson("/api/issues/{$issue->identifier}")->assertOk();

    expect($issue->fresh()->archived_at->equalTo($firstArchivedAt))->toBeTrue();
});

it('requires authentication', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $this->deleteJson("/api/issues/{$issue->identifier}")->assertUnauthorized();
    expect($issue->fresh()->archived_at)->toBeNull();
});
