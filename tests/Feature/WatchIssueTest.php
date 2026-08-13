<?php

declare(strict_types=1);

use App\Actions\AddCommentAction;
use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Notifications\IssueNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
    $this->issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);
});

it('watches and unwatches an issue', function () {
    $this->actingAs($this->user)->post('/issues/THI-1/watch')->assertRedirect();
    expect($this->issue->watchers()->wherePivot('watching', true)->count())->toBe(1);

    $this->actingAs($this->user)->delete('/issues/THI-1/watch')->assertRedirect();
    expect($this->issue->watchers()->wherePivot('watching', true)->count())->toBe(0);
});

it('is idempotent', function () {
    $this->actingAs($this->user)->post('/issues/THI-1/watch');
    $this->actingAs($this->user)->post('/issues/THI-1/watch');

    expect($this->issue->watchers()->count())->toBe(1);
});

it('subscribes whoever filed the issue', function () {
    // A fresh project: CreateIssueAction allocates number 1, which would
    // collide with the issue beforeEach already made.
    $project = Project::factory()->create(['key' => 'NEW']);
    $issue = (new CreateIssueAction)->handle($project, 'Filed by me', IssueType::Feature, owner: $this->user);

    expect($issue->watchers()->wherePivot('watching', true)->pluck('users.id')->all())->toBe([$this->user->id]);
});

it('does not fall over when a service account files with no human owner', function () {
    $project = Project::factory()->create(['key' => 'NEW']);
    $issue = (new CreateIssueAction)->handle($project, 'Filed by a robot', IssueType::Feature);

    expect($issue->watchers()->count())->toBe(0);
});

it('subscribes a commenter', function () {
    $commenter = member($this->project);

    app(AddCommentAction::class)->handle($this->issue, $commenter, 'Looking at this now');

    expect($this->issue->watchers()->wherePivot('watching', true)->pluck('users.id')->all())
        ->toContain($commenter->id);
});

it('subscribes a new assignee', function () {
    $assignee = member($this->project);

    $this->actingAs($this->user);
    $this->issue->forceFill(['assignee_id' => $assignee->id])->save();

    expect($this->issue->watchers()->wherePivot('watching', true)->pluck('users.id')->all())
        ->toContain($assignee->id);
});

it('never resubscribes someone who deliberately left', function () {
    $walker = member($this->project);
    $this->actingAs($walker)->delete('/issues/THI-1/watch');

    app(AddCommentAction::class)->handle($this->issue, $walker, 'One last word');

    expect($this->issue->watchers()->wherePivot('watching', true)->pluck('users.id')->all())
        ->not->toContain($walker->id);
});

it('notifies watchers on a comment, not only the assignee', function () {
    Notification::fake();
    $watcher = member($this->project);
    $this->actingAs($watcher)->post('/issues/THI-1/watch');
    $author = member($this->project);

    app(AddCommentAction::class)->handle($this->issue, $author, 'Something changed');

    Notification::assertSentTo($watcher, IssueNotification::class);
});

it('notifies watchers on a status change', function () {
    Notification::fake();
    $watcher = member($this->project);
    $this->actingAs($watcher)->post('/issues/THI-1/watch');

    $this->actingAs($this->user);
    $this->issue->forceFill(['status' => IssueStatus::Done])->save();

    Notification::assertSentTo($watcher, IssueNotification::class);
});

it('never notifies the person who caused the change', function () {
    Notification::fake();
    $this->actingAs($this->user)->post('/issues/THI-1/watch');

    $this->actingAs($this->user);
    $this->issue->forceFill(['status' => IssueStatus::Done])->save();

    Notification::assertNotSentTo($this->user, IssueNotification::class);
});

it('notifies the assignee even when they never pressed watch', function () {
    Notification::fake();
    $assignee = member($this->project);
    $this->issue->forceFill(['assignee_id' => $assignee->id])->saveQuietly();
    $author = member($this->project);

    app(AddCommentAction::class)->handle($this->issue, $author, 'For your attention');

    Notification::assertSentTo($assignee, IssueNotification::class);
});

it('does not notify a mentioned person twice', function () {
    Notification::fake();
    $mentioned = member($this->project);
    $this->actingAs($mentioned)->post('/issues/THI-1/watch');
    $author = member($this->project);

    app(AddCommentAction::class)->handle($this->issue, $author, "Hey @{$mentioned->name}, look at this");

    Notification::assertSentToTimes($mentioned, IssueNotification::class, 1);
});

it('reports watcher state on the issue page', function () {
    $this->actingAs($this->user)->post('/issues/THI-1/watch');

    $this->actingAs($this->user)
        ->get('/issues/THI-1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('issue.watching', true)
            ->where('issue.watcherCount', 1));
});

it('reports watching false for someone who does not', function () {
    $other = member($this->project);
    $this->actingAs($other)->post('/issues/THI-1/watch');

    $this->actingAs($this->user)
        ->get('/issues/THI-1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('issue.watching', false)
            ->where('issue.watcherCount', 1));
});

it('reports the count on the API detail', function () {
    $this->actingAs($this->user)->post('/issues/THI-1/watch');

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/issues/THI-1')
        ->assertOk()
        ->assertJsonPath('watchers', 1);
});

it('is closed to someone who cannot see the issue', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->post('/issues/THI-1/watch')->assertForbidden();
});

it('drops watchers when the issue is deleted', function () {
    $this->actingAs($this->user)->post('/issues/THI-1/watch');

    $this->issue->delete();

    expect(DB::table('issue_watchers')->count())->toBe(0);
});
