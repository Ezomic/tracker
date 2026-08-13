<?php

declare(strict_types=1);

use App\Enums\ProjectLevel;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->author = member($this->project);
    $this->issue = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1']);
    $this->comment = Comment::factory()->for($this->issue)->for($this->author, 'user')->create(['body' => 'Origianl typo']);
});

it('lets the author edit their own comment', function () {
    $this->actingAs($this->author)
        ->patch("/issues/THI-1/comments/{$this->comment->id}", ['body' => 'Original, fixed'])
        ->assertRedirect();

    $fresh = $this->comment->fresh();
    expect($fresh->body)->toBe('Original, fixed')
        ->and($fresh->edited_at)->not->toBeNull();
});

it('leaves edited_at null on a comment that was never edited', function () {
    expect($this->comment->edited_at)->toBeNull();
});

it('does not let someone else edit, even a project admin', function () {
    $admin = member($this->project, ProjectLevel::Admin);

    $this->actingAs($admin)
        ->patch("/issues/THI-1/comments/{$this->comment->id}", ['body' => 'Words in your mouth'])
        ->assertForbidden();

    expect($this->comment->fresh()->body)->toBe('Origianl typo');
});

it('still lets a project admin remove a comment they cannot edit', function () {
    $admin = member($this->project, ProjectLevel::Admin);

    $this->actingAs($admin)
        ->delete("/issues/THI-1/comments/{$this->comment->id}")
        ->assertRedirect();

    expect(Comment::query()->whereKey($this->comment->id)->exists())->toBeFalse();
});

it('rejects an empty body', function () {
    $this->actingAs($this->author)
        ->patch("/issues/THI-1/comments/{$this->comment->id}", ['body' => ''])
        ->assertSessionHasErrors('body');

    expect($this->comment->fresh()->body)->toBe('Origianl typo');
});

it('refuses a comment belonging to another issue', function () {
    $other = Issue::factory()->for($this->project)->create(['identifier' => 'THI-2']);

    $this->actingAs($this->author)
        ->patch("/issues/{$other->identifier}/comments/{$this->comment->id}", ['body' => 'Wrong issue'])
        ->assertNotFound();
});

it('never notifies on an edit, so editing cannot become a silent ping', function () {
    Notification::fake();
    $assignee = member($this->project);
    $this->issue->forceFill(['assignee_id' => $assignee->id])->save();

    $this->actingAs($this->author)
        ->patch("/issues/THI-1/comments/{$this->comment->id}", ['body' => 'Now mentioning @'.$assignee->name])
        ->assertRedirect();

    Notification::assertNothingSentTo($assignee);
});

it('sends the edited timestamp to the issue page', function () {
    $this->actingAs($this->author)->patch("/issues/THI-1/comments/{$this->comment->id}", ['body' => 'Fixed']);

    $this->actingAs($this->author)
        ->get('/issues/THI-1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('issue.comments.0.editedAt', fn ($v) => $v !== null));
});

it('edits over the API', function () {
    $response = $this->actingAs($this->author, 'sanctum')
        ->patchJson("/api/issues/THI-1/comments/{$this->comment->id}", ['body' => 'Fixed over the API']);

    $response->assertOk()->assertJsonPath('body', 'Fixed over the API');
    expect($response->json('editedAt'))->not->toBeNull();
});

it('refuses an API edit by anyone but the author', function () {
    $stranger = member($this->project);

    $this->actingAs($stranger, 'sanctum')
        ->patchJson("/api/issues/THI-1/comments/{$this->comment->id}", ['body' => 'Not mine'])
        ->assertForbidden();
});

it('reports editedAt on the API comment list', function () {
    $this->actingAs($this->author, 'sanctum')
        ->patchJson("/api/issues/THI-1/comments/{$this->comment->id}", ['body' => 'Fixed']);

    $response = $this->actingAs($this->author, 'sanctum')->getJson('/api/issues/THI-1/comments');

    expect($response->json('0.editedAt'))->not->toBeNull();
});
