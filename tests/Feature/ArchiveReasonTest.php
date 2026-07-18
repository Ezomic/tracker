<?php

declare(strict_types=1);

use App\Actions\ArchiveDoneIssuesAction;
use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Models\Project;

it('archives an issue with a reason', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/archive", ['reason' => 'Superseded by THI-58'])
        ->assertRedirect();

    expect($issue->fresh())
        ->archived_at->not->toBeNull()
        ->archive_reason->toBe('Superseded by THI-58');
});

it('archives without a reason', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/archive")
        ->assertRedirect();

    expect($issue->fresh())
        ->archived_at->not->toBeNull()
        ->archive_reason->toBeNull();
});

it('unarchives an issue and clears the reason', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $issue->forceFill(['archived_at' => now(), 'archive_reason' => 'old'])->save();

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/unarchive")
        ->assertRedirect();

    expect($issue->fresh())
        ->archived_at->toBeNull()
        ->archive_reason->toBeNull();
});

it('forbids a read member from archiving', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $reader = member($project, ProjectLevel::Read);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($reader)
        ->post("/issues/{$issue->identifier}/archive", ['reason' => 'nope'])
        ->assertForbidden();

    expect($issue->fresh()->archived_at)->toBeNull();
});

it('exposes the archive reason and permission on the issue page', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $issue->forceFill(['archived_at' => now(), 'archive_reason' => 'duplicate'])->save();

    $this->actingAs($user)->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->where('issue.archiveReason', 'duplicate')
            ->where('canArchive', true)
        );
});

it('auto-archive records a default reason', function () {
    $project = Project::factory()->create(['key' => 'THI', 'archive_after_days' => 7]);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $issue->forceFill([
        'status' => IssueStatus::Done,
        'closed_at' => now()->subDays(8),
    ])->save();

    (new ArchiveDoneIssuesAction)->handle();

    expect($issue->fresh())
        ->archived_at->not->toBeNull()
        ->archive_reason->toBe('Auto-archived 7 days after being done');
});
