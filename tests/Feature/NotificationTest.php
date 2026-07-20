<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\User;
use App\Notifications\IssueNotification;

it('notifies a mentioned project member', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $author = member($project, ProjectLevel::Write);
    $dana = joinProjects(
        User::factory()->create(['name' => 'Dana Dev', 'email' => 'dana@example.com']),
        $project,
        ProjectLevel::Write,
    );
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($author)
        ->post("/issues/{$issue->identifier}/comments", ['body' => 'Hey @dana can you look?'])
        ->assertRedirect();

    expect($dana->notifications()->count())->toBe(1)
        ->and($dana->notifications()->first()->data['type'])->toBe('comment_mention')
        ->and($dana->notifications()->first()->data['issueIdentifier'])->toBe($issue->identifier);
});

it('does not notify you for mentioning yourself', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $author = joinProjects(
        User::factory()->create(['name' => 'Robin', 'email' => 'robin@example.com']),
        $project,
        ProjectLevel::Write,
    );
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($author)->post("/issues/{$issue->identifier}/comments", ['body' => '@robin note to self']);

    expect($author->notifications()->count())->toBe(0);
});

it('notifies the assignee when someone else comments', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $author = member($project, ProjectLevel::Write);
    $assignee = joinProjects(User::factory()->create(), $project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    // Assigned outside an authenticated request, so no assignment notification fires here.
    $issue->forceFill(['assignee_id' => $assignee->id])->save();

    $this->actingAs($author)->post("/issues/{$issue->identifier}/comments", ['body' => 'progress update']);

    expect($assignee->notifications()->count())->toBe(1)
        ->and($assignee->notifications()->first()->data['type'])->toBe('issue_commented');
});

it('notifies a newly assigned user but not a self-assignment', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $actor = member($project, ProjectLevel::Admin);
    $assignee = joinProjects(User::factory()->create(), $project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($actor)->patch("/issues/{$issue->identifier}", [
        'title' => 'Task', 'type' => 'feature', 'priority' => 'none', 'assignee_id' => $assignee->id,
    ]);
    expect($assignee->notifications()->count())->toBe(1)
        ->and($assignee->notifications()->first()->data['type'])->toBe('issue_assigned');

    $this->actingAs($actor)->patch("/issues/{$issue->identifier}", [
        'title' => 'Task', 'type' => 'feature', 'priority' => 'none', 'assignee_id' => $actor->id,
    ]);
    expect($actor->notifications()->count())->toBe(0);
});

it('marks a single notification as read for its owner only', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $other = member($project, ProjectLevel::Write);
    $actor = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $user->notify(new IssueNotification('issue_assigned', $issue, $actor));
    $id = $user->notifications()->first()->id;

    // A different user cannot mark it read.
    $this->actingAs($other)->patch("/notifications/{$id}/read");
    expect($user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)->patch("/notifications/{$id}/read")->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications as read', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $actor = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $user->notify(new IssueNotification('issue_assigned', $issue, $actor));
    $user->notify(new IssueNotification('comment_mention', $issue, $actor));

    $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});
