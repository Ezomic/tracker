<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Support\Duration;

it('parses and formats durations', function () {
    expect(Duration::toMinutes('1h 30m'))->toBe(90)
        ->and(Duration::toMinutes('2h'))->toBe(120)
        ->and(Duration::toMinutes('45m'))->toBe(45)
        ->and(Duration::toMinutes('1.5h'))->toBe(90)
        ->and(Duration::toMinutes('90'))->toBe(90)
        ->and(Duration::toMinutes('nonsense'))->toBeNull()
        ->and(Duration::toMinutes(''))->toBeNull()
        ->and(Duration::format(90))->toBe('1h 30m')
        ->and(Duration::format(120))->toBe('2h')
        ->and(Duration::format(45))->toBe('45m');
});

it('logs time and totals it on the issue', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/time", ['duration' => '1h 30m'])
        ->assertRedirect();
    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/time", ['duration' => '45m', 'note' => 'review'])
        ->assertRedirect();

    expect($issue->timeEntries()->sum('minutes'))->toBe(135)
        ->and($issue->timeEntries()->first()->user_id)->toBe($user->id);

    $this->actingAs($user)->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->where('issue.loggedMinutes', 135)
            ->has('issue.timeEntries', 2)
        );
});

it('defaults the entry date to today when omitted', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->post("/issues/{$issue->identifier}/time", ['duration' => '30m']);

    expect($issue->timeEntries()->first()->spent_on->toDateString())->toBe(now()->toDateString());
});

it('rejects an invalid duration when logging', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/time", ['duration' => 'soon'])
        ->assertSessionHasErrors('duration');

    expect($issue->timeEntries()->count())->toBe(0);
});

it('forbids a read member from logging time', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $reader = member($project, ProjectLevel::Read);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($reader)
        ->post("/issues/{$issue->identifier}/time", ['duration' => '1h'])
        ->assertForbidden();

    expect($issue->timeEntries()->count())->toBe(0);
});

it('saves an estimate as minutes', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", [
        'title' => 'Task',
        'type' => 'feature',
        'priority' => 'none',
        'estimate' => '5h',
    ])->assertRedirect();

    expect($issue->fresh()->estimate_minutes)->toBe(300);
});

it('rejects an invalid estimate', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", [
        'title' => 'Task',
        'type' => 'feature',
        'priority' => 'none',
        'estimate' => 'whenever',
    ])->assertSessionHasErrors('estimate');
});

it('lets a member delete their own entry but not another member entry', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $writer = member($project, ProjectLevel::Write);
    $other = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $entry = TimeEntry::factory()->for($issue)->create(['user_id' => $writer->id, 'minutes' => 60]);

    // A different write member cannot delete someone else's entry.
    $this->actingAs($other)
        ->delete("/issues/{$issue->identifier}/time/{$entry->id}")
        ->assertForbidden();

    // The owner can.
    $this->actingAs($writer)
        ->delete("/issues/{$issue->identifier}/time/{$entry->id}")
        ->assertRedirect();

    expect(TimeEntry::query()->count())->toBe(0);
});

it('lets a project admin delete anyone else entry', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $admin = member($project, ProjectLevel::Admin);
    $writer = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $entry = TimeEntry::factory()->for($issue)->create(['user_id' => $writer->id, 'minutes' => 60]);

    $this->actingAs($admin)
        ->delete("/issues/{$issue->identifier}/time/{$entry->id}")
        ->assertRedirect();

    expect(TimeEntry::query()->count())->toBe(0);
});

it('404s when the entry belongs to a different issue', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $admin = member($project, ProjectLevel::Admin);
    $issue = (new CreateIssueAction)->handle($project, 'One', IssueType::Feature);
    $other = (new CreateIssueAction)->handle($project, 'Two', IssueType::Feature);
    $entry = TimeEntry::factory()->for($other)->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->delete("/issues/{$issue->identifier}/time/{$entry->id}")
        ->assertNotFound();
});

it('rolls up logged time per project on the projects page', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Admin);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    TimeEntry::factory()->for($issue)->create(['user_id' => $user->id, 'minutes' => 90]);
    TimeEntry::factory()->for($issue)->create(['user_id' => $user->id, 'minutes' => 30]);

    $this->actingAs($user)->get('/projects')
        ->assertInertia(fn ($page) => $page->where('projects.0.loggedMinutes', 120));
});
