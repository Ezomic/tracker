<?php

declare(strict_types=1);

use App\Actions\ArchiveDoneIssuesAction;
use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Project;

it('archives a done issue closed more than 24 hours ago', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subHours(25)])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(1)
        ->and($issue->fresh()->archived_at)->not->toBeNull();
});

it('does not archive a done issue closed less than 24 hours ago', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subHours(23)])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(0)
        ->and($issue->fresh()->archived_at)->toBeNull();
});

it('does not archive issues that are not done', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::InReview])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(0);
});

it('does not re-archive an already archived issue', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill([
        'status' => IssueStatus::Done,
        'closed_at' => now()->subHours(48),
        'archived_at' => now()->subHours(1),
    ])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(0);
});

it('excludes archived issues from the index and board', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $visible = (new CreateIssueAction)->handle($team, 'Visible issue', IssueType::Feature);
    $archived = (new CreateIssueAction)->handle($team, 'Archived issue', IssueType::Feature);
    $archived->forceFill(['status' => IssueStatus::Done, 'archived_at' => now()])->save();

    $user = member($team);

    $this->actingAs($user)->get('/issues')->assertInertia(fn ($page) => $page
        ->where('issues.0.identifier', $visible->identifier)
        ->has('issues', 1)
    );

    $this->actingAs($user)->get('/issues/board')->assertInertia(fn ($page) => $page
        ->where('issues.0.identifier', $visible->identifier)
        ->has('issues', 1)
    );
});

it('still shows an archived issue on its own detail page', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'Archived issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::Done, 'archived_at' => now()])->save();

    $this->actingAs(member($team))
        ->get("/issues/{$issue->identifier}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('issue.archivedAt', fn ($value) => $value !== null));
});

it("honours a project's custom archive duration", function () {
    $team = Project::factory()->create(['key' => 'THI', 'archive_after_days' => 7]);
    $recent = (new CreateIssueAction)->handle($team, 'Recent', IssueType::Feature);
    $recent->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(5)])->save();
    $old = (new CreateIssueAction)->handle($team, 'Old', IssueType::Feature);
    $old->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(8)])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(1)
        ->and($recent->fresh()->archived_at)->toBeNull()
        ->and($old->fresh()->archived_at)->not->toBeNull();
});

it('never archives issues of a project with a null archive duration', function () {
    $team = Project::factory()->create(['key' => 'THI', 'archive_after_days' => null]);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);
    $issue->forceFill(['status' => IssueStatus::Done, 'closed_at' => now()->subDays(100)])->save();

    $count = (new ArchiveDoneIssuesAction)->handle();

    expect($count)->toBe(0)
        ->and($issue->fresh()->archived_at)->toBeNull();
});
