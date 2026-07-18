<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Models\Comment;
use App\Models\Project;
use App\Models\User;

it('lets anyone who can view the issue post a comment', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $reader = member($project, ProjectLevel::Read);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($reader)
        ->post("/issues/{$issue->identifier}/comments", ['body' => 'Looks good to me'])
        ->assertRedirect();

    $comment = Comment::query()->firstOrFail();
    expect($comment->body)->toBe('Looks good to me')
        ->and($comment->user_id)->toBe($reader->id)
        ->and($comment->issue_id)->toBe($issue->id);
});

it('lists comments on the issue oldest first', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    Comment::factory()->for($issue)->create(['body' => 'first', 'created_at' => now()->subHour()]);
    Comment::factory()->for($issue)->create(['body' => 'second', 'created_at' => now()]);

    $this->actingAs($user)->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->has('issue.comments', 2)
            ->where('issue.comments.0.body', 'first')
            ->where('issue.comments.1.body', 'second')
        );
});

it('requires a body', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs($user)
        ->post("/issues/{$issue->identifier}/comments", ['body' => ''])
        ->assertSessionHasErrors('body');

    expect(Comment::query()->count())->toBe(0);
});

it('forbids a non-member from commenting', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->actingAs(User::factory()->create())
        ->post("/issues/{$issue->identifier}/comments", ['body' => 'hi'])
        ->assertForbidden();

    expect(Comment::query()->count())->toBe(0);
});

it('lets a member delete their own comment but not another member comment', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $author = member($project, ProjectLevel::Write);
    $other = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $comment = Comment::factory()->for($issue)->create(['user_id' => $author->id]);

    $this->actingAs($other)
        ->delete("/issues/{$issue->identifier}/comments/{$comment->id}")
        ->assertForbidden();

    $this->actingAs($author)
        ->delete("/issues/{$issue->identifier}/comments/{$comment->id}")
        ->assertRedirect();

    expect(Comment::query()->count())->toBe(0);
});

it('lets a project admin delete anyone else comment', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $admin = member($project, ProjectLevel::Admin);
    $author = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $comment = Comment::factory()->for($issue)->create(['user_id' => $author->id]);

    $this->actingAs($admin)
        ->delete("/issues/{$issue->identifier}/comments/{$comment->id}")
        ->assertRedirect();

    expect(Comment::query()->count())->toBe(0);
});

it('404s when the comment belongs to a different issue', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $admin = member($project, ProjectLevel::Admin);
    $issue = (new CreateIssueAction)->handle($project, 'One', IssueType::Feature);
    $other = (new CreateIssueAction)->handle($project, 'Two', IssueType::Feature);
    $comment = Comment::factory()->for($other)->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->delete("/issues/{$issue->identifier}/comments/{$comment->id}")
        ->assertNotFound();
});
