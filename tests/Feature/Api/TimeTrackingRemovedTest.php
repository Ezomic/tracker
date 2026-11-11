<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Issue;

it('ignores an estimate sent when filing an issue', function () {
    [$organization, $user] = organizationWith();
    projectInOrganization($organization, $user, ['key' => 'THI']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'project' => 'THI',
        'title' => 'Still estimated by an old client',
        'type' => 'feature',
        'estimate' => 'soon',
    ])->assertCreated();

    expect(Issue::query()->where('title', 'Still estimated by an old client')->exists())->toBeTrue();
});

it('ignores an estimate sent when updating an issue', function () {
    [$organization, $user] = organizationWith();
    $project = projectInOrganization($organization, $user, ['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", ['title' => 'Retitled', 'estimate' => '4h'])
        ->assertOk()
        ->assertJsonMissingPath('estimate_minutes');

    expect($issue->fresh()->title)->toBe('Retitled');
});

it('no longer serves the time endpoints', function () {
    [$organization, $user] = organizationWith();
    $project = projectInOrganization($organization, $user, ['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);

    $this->actingAs($user, 'sanctum')->getJson("/api/issues/{$issue->identifier}/time")->assertNotFound();
    $this->actingAs($user, 'sanctum')->postJson("/api/issues/{$issue->identifier}/time", ['duration' => '1h'])->assertNotFound();
    $this->actingAs($user, 'sanctum')->deleteJson("/api/issues/{$issue->identifier}/time/1")->assertNotFound();
});
