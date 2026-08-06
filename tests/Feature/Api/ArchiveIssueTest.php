<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\User;

it('archives an issue and stamps archived_at', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/issues/{$issue->identifier}");

    $response->assertOk()->assertJson(['identifier' => $issue->identifier]);
    expect($issue->fresh()->archived_at)->not->toBeNull();
});

it('drops an archived issue from the issues list', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
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
    joinProjects($user, $project);
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

it('restores an archived issue', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);
    $issue->forceFill(['archived_at' => now(), 'archive_reason' => 'duplicate'])->save();

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/issues/{$issue->identifier}/restore");

    $response->assertOk()->assertJson([
        'identifier' => $issue->identifier,
        'archived_at' => null,
    ]);
    expect($issue->fresh()->archived_at)->toBeNull()
        ->and($issue->fresh()->archive_reason)->toBeNull();
});

it('puts a restored issue back on the issues list', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/issues/{$issue->identifier}")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/issues/{$issue->identifier}/restore")->assertOk();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/issues')
        ->assertOk()
        ->assertJsonFragment(['identifier' => $issue->identifier]);
});

it('is idempotent when the issue was never archived', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $this->actingAs($user, 'sanctum')->postJson("/api/issues/{$issue->identifier}/restore")->assertOk();

    expect($issue->fresh()->archived_at)->toBeNull();
});

it('records an unarchived event on the timeline', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);
    $issue->forceFill(['archived_at' => now()])->save();

    $this->actingAs($user, 'sanctum')->postJson("/api/issues/{$issue->identifier}/restore")->assertOk();

    expect($issue->activities()->where('type', 'unarchived')->exists())->toBeTrue();
});

it('takes the same access level as archiving', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);
    $issue->forceFill(['archived_at' => now()])->save();
    $writer = member($project, ProjectLevel::Write);

    $this->actingAs($writer, 'sanctum')
        ->postJson("/api/issues/{$issue->identifier}/restore")
        ->assertForbidden();

    expect($issue->fresh()->archived_at)->not->toBeNull();
});

it('requires authentication to restore', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);
    $issue->forceFill(['archived_at' => now()])->save();

    $this->postJson("/api/issues/{$issue->identifier}/restore")->assertUnauthorized();
    expect($issue->fresh()->archived_at)->not->toBeNull();
});

it('lists archived issues on request', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    $live = (new CreateIssueAction)->handle($project, 'Live', IssueType::Feature);
    $gone = (new CreateIssueAction)->handle($project, 'Gone', IssueType::Feature);
    $gone->forceFill(['archived_at' => now(), 'archive_reason' => 'duplicate'])->save();

    $acting = $this->actingAs($user, 'sanctum');

    expect($acting->getJson('/api/issues')->json('data.*.identifier'))
        ->toBe([$live->identifier]);
    expect($acting->getJson('/api/issues?archived=include')->json('data.*.identifier'))
        ->toBe([$live->identifier, $gone->identifier]);
    expect($acting->getJson('/api/issues?archived=only')->json('data.*.identifier'))
        ->toBe([$gone->identifier]);
});

it('rejects an unknown archived filter', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/issues?archived=maybe')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('archived');
});

it('reports archived_at and the reason on the issue detail', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $project);
    $issue = (new CreateIssueAction)->handle($project, 'Issue', IssueType::Feature);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/issues/{$issue->identifier}", ['reason' => 'duplicate'])
        ->assertOk();

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/issues/{$issue->identifier}");

    $response->assertOk()->assertJsonFragment(['archive_reason' => 'duplicate']);
    expect($response->json('archived_at'))->not->toBeNull();
});
