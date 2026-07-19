<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Models\Project;
use App\Models\User;

it('assigns an existing issue to an epic by identifier', function () {
    $user = User::factory()->create();
    $team = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $team);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $issue = (new CreateIssueAction)->handle($team, 'Standalone', IssueType::Feature);

    $response = $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}", [
        'parent' => $epic->identifier,
    ]);

    $response->assertOk()->assertJson([
        'identifier' => $issue->identifier,
        'parent' => $epic->identifier,
    ]);
    expect($issue->fresh()->parent_id)->toBe($epic->id);
});

it('detaches the parent when parent is submitted null', function () {
    $user = User::factory()->create();
    $team = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $team);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $issue = (new CreateIssueAction)->handle($team, 'Child', IssueType::Feature, parent: $epic);

    $response = $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}", [
        'parent' => null,
    ]);

    $response->assertOk()->assertJson(['parent' => null]);
    expect($issue->fresh()->parent_id)->toBeNull();
});

it('is a no-op when no fields are submitted', function () {
    $user = User::factory()->create();
    $team = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $team);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $issue = (new CreateIssueAction)->handle($team, 'Child', IssueType::Feature, parent: $epic);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/issues/{$issue->identifier}", [])
        ->assertOk();

    expect($issue->fresh()->parent_id)->toBe($epic->id);
});

it('updates the title without touching the parent', function () {
    $user = User::factory()->create();
    $team = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $team);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $issue = (new CreateIssueAction)->handle($team, 'Child', IssueType::Feature, parent: $epic);

    $response = $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}", [
        'title' => 'Renamed title',
    ]);

    $response->assertOk()->assertJson(['title' => 'Renamed title']);
    $issue->refresh();
    expect($issue->title)->toBe('Renamed title');
    expect($issue->parent_id)->toBe($epic->id);
});

it('updates the description', function () {
    $user = User::factory()->create();
    $team = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $team);
    $issue = (new CreateIssueAction)->handle($team, 'Issue', IssueType::Feature, description: 'Old');

    $response = $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}", [
        'description' => "## Steps to reproduce\n1. Do the thing",
    ]);

    $response->assertOk()->assertJson(['description' => "## Steps to reproduce\n1. Do the thing"]);
    expect($issue->fresh()->description)->toBe("## Steps to reproduce\n1. Do the thing");
});

it('clears the description when submitted null', function () {
    $user = User::factory()->create();
    $team = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $team);
    $issue = (new CreateIssueAction)->handle($team, 'Issue', IssueType::Feature, description: 'Old');

    $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}", [
        'description' => null,
    ])->assertOk()->assertJson(['description' => null]);

    expect($issue->fresh()->description)->toBeNull();
});

it('rejects an issue being assigned as its own epic', function () {
    $user = User::factory()->create();
    $team = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $team);
    $issue = (new CreateIssueAction)->handle($team, 'Issue', IssueType::Feature);

    $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}", [
        'parent' => $issue->identifier,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent');
});

it('rejects a parent that already sits under another epic', function () {
    $user = User::factory()->create();
    $team = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $team);
    $epic = (new CreateIssueAction)->handle($team, 'Epic', IssueType::Feature);
    $child = (new CreateIssueAction)->handle($team, 'Child', IssueType::Feature, parent: $epic);
    $issue = (new CreateIssueAction)->handle($team, 'Standalone', IssueType::Feature);

    $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}", [
        'parent' => $child->identifier,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent');
});

it('rejects assigning a parent to an issue that already has sub-issues', function () {
    $user = User::factory()->create();
    $team = Project::factory()->create(['key' => 'THI']);
    joinProjects($user, $team);
    $epicA = (new CreateIssueAction)->handle($team, 'Epic A', IssueType::Feature);
    $epicB = (new CreateIssueAction)->handle($team, 'Epic B', IssueType::Feature);
    (new CreateIssueAction)->handle($team, 'Child of A', IssueType::Feature, parent: $epicA);

    $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$epicA->identifier}", [
        'parent' => $epicB->identifier,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent');
});

it('rejects a parent from a different team', function () {
    $user = User::factory()->create();
    $thi = Project::factory()->create(['key' => 'THI']);
    $billr = Project::factory()->create(['key' => 'BILLR']);
    joinProjects($user, [$thi, $billr]);
    $issue = (new CreateIssueAction)->handle($thi, 'Issue', IssueType::Feature);
    $epic = (new CreateIssueAction)->handle($billr, 'Epic', IssueType::Feature);

    $this->actingAs($user, 'sanctum')->patchJson("/api/issues/{$issue->identifier}", [
        'parent' => $epic->identifier,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent');
});

it('requires authentication', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'Issue', IssueType::Feature);

    $this->patchJson("/api/issues/{$issue->identifier}", [
        'parent' => null,
    ])->assertUnauthorized();
});
