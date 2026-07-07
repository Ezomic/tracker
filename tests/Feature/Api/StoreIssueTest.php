<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;

it('creates an issue and returns identifier, url, and branch_name', function () {
    $user = User::factory()->create();
    Team::factory()->create(['key' => 'THI', 'next_number' => 0]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'team' => 'THI',
        'title' => 'Add per-lesson quiz question pools',
        'type' => 'feature',
        'description' => 'Randomize the quiz question shown on replay.',
    ]);

    $response->assertCreated()->assertJson([
        'identifier' => 'THI-1',
        'url' => url('/issues/THI-1'),
        'branch_name' => 'feature/THI-1-add-per-lesson-quiz-question-pools',
    ]);
});

it('rejects unauthenticated requests', function () {
    Team::factory()->create(['key' => 'THI']);

    $this->postJson('/api/issues', [
        'team' => 'THI',
        'title' => 'Anything',
        'type' => 'feature',
    ])->assertUnauthorized();
});

it('returns 422 for an unknown team key rather than auto-creating it', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'team' => 'NOPE',
        'title' => 'Anything',
        'type' => 'feature',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('team');
    expect(Team::query()->where('key', 'NOPE')->exists())->toBeFalse();
});

it('returns 422 for a blank title', function () {
    $user = User::factory()->create();
    Team::factory()->create(['key' => 'THI']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'team' => 'THI',
        'title' => '',
        'type' => 'feature',
    ])->assertUnprocessable()->assertJsonValidationErrors('title');
});

it('returns 422 for an invalid type', function () {
    $user = User::factory()->create();
    Team::factory()->create(['key' => 'THI']);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'team' => 'THI',
        'title' => 'Anything',
        'type' => 'chore',
    ])->assertUnprocessable()->assertJsonValidationErrors('type');
});
