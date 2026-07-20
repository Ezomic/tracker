<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Project;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function updatePayload(App\Models\Issue $issue, array $overrides = []): array
{
    return array_merge([
        'title' => $issue->title,
        'type' => $issue->type->value,
        'priority' => $issue->priority->value,
    ], $overrides);
}

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

it('records a priority change with from and to', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", updatePayload($issue, [
        'priority' => 'high',
    ]));

    $activity = $issue->activities()->where('type', 'priority_changed')->firstOrFail();
    expect($activity->data)->toBe(['from' => 'none', 'to' => 'high']);
});

it('records a type change', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", updatePayload($issue, [
        'type' => 'fix',
    ]));

    $activity = $issue->activities()->where('type', 'type_changed')->firstOrFail();
    expect($activity->data)->toBe(['from' => 'feature', 'to' => 'fix']);
});

it('records an estimate change in minutes', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", updatePayload($issue, [
        'estimate' => '2h 30m',
    ]));

    $activity = $issue->activities()->where('type', 'estimate_changed')->firstOrFail();
    expect($activity->data)->toBe(['from' => null, 'to' => 150]);
});

it('records a parent change with identifiers', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $epic = (new CreateIssueAction)->handle($project, 'Epic', IssueType::Feature);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", updatePayload($issue, [
        'parent_id' => $epic->id,
    ]));

    $activity = $issue->activities()->where('type', 'parent_changed')->firstOrFail();
    expect($activity->data)->toBe(['from' => null, 'to' => $epic->identifier]);
});

it('collapses repeated title edits by the same user into one entry', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)->patch("/issues/{$issue->identifier}", updatePayload($issue, ['title' => 'First rename']));
    $this->travel(2)->minutes();
    $this->actingAs($user)->patch("/issues/{$issue->identifier}", updatePayload($issue->fresh(), ['title' => 'Second rename']));

    $renames = $issue->activities()->where('type', 'renamed')->get();
    expect($renames)->toHaveCount(1)
        ->and($renames->first()->data)->toBe(['from' => 'First rename', 'to' => 'Second rename']);
});

it('records label added and removed events', function () {
    [$org, $owner] = organizationWith();
    $project = projectInOrganization($org, $owner, ['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $bug = Label::factory()->for($org)->create(['name' => 'bug']);

    $this->actingAs($owner)->patch("/issues/{$issue->identifier}", updatePayload($issue, ['labels' => [$bug->id]]));
    expect($issue->activities()->where('type', 'label_added')->firstOrFail()->data)->toBe(['name' => 'bug']);

    $this->actingAs($owner)->patch("/issues/{$issue->identifier}", updatePayload($issue, ['labels' => []]));
    expect($issue->activities()->where('type', 'label_removed')->firstOrFail()->data)->toBe(['name' => 'bug']);
});

it('records a pr_merged activity from a merged pull request webhook', function () {
    config(['services.github.webhook_secret' => 'test-secret']);
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $body = [
        'action' => 'closed',
        'pull_request' => [
            'head' => ['ref' => $issue->branch_name],
            'html_url' => 'https://github.com/Ezomic/tracker/pull/42',
            'merged' => true,
        ],
    ];

    $this->postJson('/api/webhooks/github', $body, [
        'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', (string) json_encode($body), 'test-secret'),
        'X-GitHub-Event' => 'pull_request',
    ])->assertNoContent();

    $activity = $issue->activities()->where('type', 'pr_merged')->firstOrFail();
    expect($activity->data)->toBe(['url' => 'https://github.com/Ezomic/tracker/pull/42'])
        ->and($activity->user_id)->toBeNull();
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
