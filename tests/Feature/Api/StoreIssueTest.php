<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Issue;
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

it('creates an issue under an epic when a parent identifier is given', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['key' => 'THI', 'next_number' => 0]);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'team' => 'THI',
        'title' => 'Child',
        'type' => 'feature',
        'parent' => $epic->identifier,
    ]);

    $response->assertCreated()->assertJson(['parent' => $epic->identifier]);
    expect(Issue::query()->where('title', 'Child')->first()->parent_id)->toBe($epic->id);
});

it('rejects a parent that already sits under another epic', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['key' => 'THI']);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $child = (new CreateIssueAction)->handle($team, 'Child', IssueType::Feature, parent: $epic);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'team' => 'THI',
        'title' => 'Grandchild',
        'type' => 'feature',
        'parent' => $child->identifier,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent');
});

it('rejects a parent from a different team', function () {
    $user = User::factory()->create();
    $thi = Team::factory()->create(['key' => 'THI']);
    $billr = Team::factory()->create(['key' => 'BILLR']);
    $epic = (new CreateIssueAction)->handle($billr, 'Epic', IssueType::Feature);

    $this->actingAs($user, 'sanctum')->postJson('/api/issues', [
        'team' => 'THI',
        'title' => 'Child',
        'type' => 'feature',
        'parent' => $epic->identifier,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent');

    expect($thi->fresh())->not->toBeNull();
});
