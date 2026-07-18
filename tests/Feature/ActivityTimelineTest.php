<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Models\Comment;
use App\Models\Project;

it('records a created activity when an issue is filed', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    expect($issue->activities()->where('type', 'created')->count())->toBe(1);
});

it('records a status change with from and to', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}/status", ['status' => 'in_progress']);

    $activity = $issue->activities()->where('type', 'status_changed')->firstOrFail();
    expect($activity->data)->toBe(['from' => 'backlog', 'to' => 'in_progress'])
        ->and($activity->user_id)->toBe($user->id);
});

it('records an assignment', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Admin);
    $assignee = member($project, ProjectLevel::Write);
    $assignee->forceFill(['name' => 'Dana Dev'])->save();
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", [
        'title' => 'Task',
        'type' => 'feature',
        'priority' => 'none',
        'assignee_id' => $assignee->id,
    ]);

    $activity = $issue->activities()->where('type', 'assigned')->firstOrFail();
    expect($activity->data)->toBe(['to' => 'Dana Dev']);
});

it('records archive and unarchive', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->post("/issues/{$issue->identifier}/archive", ['reason' => 'dup']);
    expect($issue->activities()->where('type', 'archived')->firstOrFail()->data)->toBe(['reason' => 'dup']);

    $this->actingAs($user)->post("/issues/{$issue->identifier}/unarchive");
    expect($issue->activities()->where('type', 'unarchived')->count())->toBe(1);
});

it('records logged time', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->post("/issues/{$issue->identifier}/time", ['duration' => '1h 30m']);

    $activity = $issue->activities()->where('type', 'time_logged')->firstOrFail();
    expect($activity->data)->toBe(['minutes' => 90])
        ->and($activity->user_id)->toBe($user->id);
});

it('merges comments and activities into a chronological timeline', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->travel(1)->minutes();
    Comment::factory()->for($issue)->create(['user_id' => $user->id, 'body' => 'first look']);

    $this->travel(1)->minutes();
    $this->actingAs($user)->patch("/issues/{$issue->identifier}/status", ['status' => 'in_progress']);

    $this->actingAs($user)->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->has('timeline', 3)
            ->where('timeline.0.kind', 'activity')
            ->where('timeline.0.type', 'created')
            ->where('timeline.1.kind', 'comment')
            ->where('timeline.1.body', 'first look')
            ->where('timeline.2.kind', 'activity')
            ->where('timeline.2.type', 'status_changed')
        );
});
