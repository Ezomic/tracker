<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

it('lists comments on an issue oldest first', function () {
    $author = User::factory()->create(['name' => 'Robbin']);
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($author, $project);
    $issue = Issue::factory()->for($project)->create(['identifier' => 'THI-1']);
    $issue->comments()->create(['user_id' => $author->id, 'body' => 'first']);
    $issue->comments()->create(['user_id' => $author->id, 'body' => 'second']);

    $this->actingAs($author, 'sanctum')->getJson('/api/issues/THI-1/comments')
        ->assertOk()
        ->assertJson([
            ['body' => 'first', 'user' => 'Robbin'],
            ['body' => 'second', 'user' => 'Robbin'],
        ]);
});

it('creates a comment via the API', function () {
    $author = User::factory()->create(['name' => 'Robbin']);
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($author, $project);
    $issue = Issue::factory()->for($project)->create(['identifier' => 'THI-2']);

    $this->actingAs($author, 'sanctum')
        ->postJson('/api/issues/THI-2/comments', ['body' => 'Deployed to prod'])
        ->assertCreated()
        ->assertJson(['body' => 'Deployed to prod', 'user' => 'Robbin']);

    expect($issue->comments()->where('body', 'Deployed to prod')->exists())->toBeTrue();
});

it('validates the comment body', function () {
    $author = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    joinProjects($author, $project);
    Issue::factory()->for($project)->create(['identifier' => 'THI-3']);

    $this->actingAs($author, 'sanctum')
        ->postJson('/api/issues/THI-3/comments', ['body' => ''])
        ->assertUnprocessable();
});

it('forbids commenting on an issue the user cannot see', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['key' => 'THI']);
    Issue::factory()->for($project)->create(['identifier' => 'THI-4']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/issues/THI-4/comments', ['body' => 'hi'])
        ->assertForbidden();
});

it('requires authentication', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    Issue::factory()->for($project)->create(['identifier' => 'THI-5']);

    $this->postJson('/api/issues/THI-5/comments', ['body' => 'hi'])->assertUnauthorized();
});
