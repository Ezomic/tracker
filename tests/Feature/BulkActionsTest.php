<?php

declare(strict_types=1);

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\ProjectLevel;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
    $this->one = Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1']);
    $this->two = Issue::factory()->for($this->project)->create(['number' => 2, 'identifier' => 'THI-2']);
});

it('sets a status across a selection in one request', function () {
    $response = $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1', 'THI-2'],
        'status' => 'done',
    ]);

    $response->assertOk();
    expect($response->json('updated'))->toBe(['THI-1', 'THI-2'])
        ->and($this->one->fresh()->status)->toBe(IssueStatus::Done)
        ->and($this->two->fresh()->status)->toBe(IssueStatus::Done);
});

it('stamps closed_at when moving to done, and clears it when moving back', function () {
    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1'],
        'status' => 'done',
    ]);
    expect($this->one->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1'],
        'status' => 'backlog',
    ]);
    expect($this->one->fresh()->closed_at)->toBeNull();
});

it('sets priority and assignee', function () {
    $assignee = member($this->project);

    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1'],
        'priority' => 'urgent',
        'assignee_id' => $assignee->id,
    ])->assertOk();

    expect($this->one->fresh()->priority)->toBe(IssuePriority::Urgent)
        ->and($this->one->fresh()->assignee_id)->toBe($assignee->id);
});

it('adds and removes labels without touching the others', function () {
    $keep = Label::factory()->create(['name' => 'keep', 'organization_id' => $this->project->organization_id]);
    $add = Label::factory()->create(['name' => 'add', 'organization_id' => $this->project->organization_id]);
    $drop = Label::factory()->create(['name' => 'drop', 'organization_id' => $this->project->organization_id]);
    $this->one->labels()->sync([$keep->id, $drop->id]);

    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1'],
        'add_labels' => ['add'],
        'remove_labels' => ['drop'],
    ])->assertOk();

    expect($this->one->fresh()->labels->pluck('name')->sort()->values()->all())->toBe(['add', 'keep']);
});

it('archives and restores', function () {
    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1'],
        'archived' => true,
    ])->assertOk();
    expect($this->one->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1'],
        'archived' => false,
    ])->assertOk();
    expect($this->one->fresh()->archived_at)->toBeNull();
});

it('leaves omitted fields alone rather than nulling them', function () {
    $assignee = member($this->project);
    $this->one->forceFill(['assignee_id' => $assignee->id, 'priority' => IssuePriority::High])->save();

    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1'],
        'status' => 'in_progress',
    ])->assertOk();

    expect($this->one->fresh()->assignee_id)->toBe($assignee->id)
        ->and($this->one->fresh()->priority)->toBe(IssuePriority::High);
});

it('skips an issue the actor cannot write, without aborting the rest', function () {
    $hidden = Issue::factory()->for(Project::factory()->create(['key' => 'SECRET']))->create(['identifier' => 'SECRET-1']);

    $response = $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1', 'SECRET-1'],
        'status' => 'done',
    ]);

    $response->assertOk();
    expect($response->json('updated'))->toBe(['THI-1'])
        ->and($response->json('skipped'))->toBe([['issue' => 'SECRET-1', 'reason' => 'no write access']])
        ->and($this->one->fresh()->status)->toBe(IssueStatus::Done)
        ->and($hidden->fresh()->status)->not->toBe(IssueStatus::Done);
});

it('reports an issue that does not exist', function () {
    $response = $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1', 'GONE-9'],
        'status' => 'done',
    ]);

    expect($response->json('skipped'))->toBe([['issue' => 'GONE-9', 'reason' => 'not found']]);
});

it('skips a read-only member entirely', function () {
    $reader = member($this->project, ProjectLevel::Read);

    $response = $this->actingAs($reader, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1'],
        'status' => 'done',
    ]);

    expect($response->json('updated'))->toBe([])
        ->and($this->one->fresh()->status)->not->toBe(IssueStatus::Done);
});

it('records activity per issue, so a bulk change does not lose the timeline', function () {
    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1', 'THI-2'],
        'status' => 'done',
    ]);

    expect($this->one->activities()->where('type', 'status_changed')->exists())->toBeTrue()
        ->and($this->two->activities()->where('type', 'status_changed')->exists())->toBeTrue();
});

it('deduplicates a repeated identifier', function () {
    $response = $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1', 'THI-1'],
        'status' => 'done',
    ]);

    expect($response->json('updated'))->toBe(['THI-1']);
});

it('requires at least one change', function () {
    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', ['issues' => ['THI-1']])
        ->assertUnprocessable();

    expect($this->one->fresh()->status)->toBe(IssueStatus::Backlog);
});

it('requires at least one issue', function () {
    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', ['issues' => [], 'status' => 'done'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('issues');
});

it('caps the selection size', function () {
    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => array_fill(0, 201, 'THI-1'),
        'status' => 'done',
    ])->assertUnprocessable()->assertJsonValidationErrors('issues');
});

it('rejects an invalid status', function () {
    $this->actingAs($this->user, 'sanctum')->patchJson('/api/issues/bulk', [
        'issues' => ['THI-1'],
        'status' => 'nonsense',
    ])->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('does not shadow the single-issue patch route', function () {
    $this->actingAs($this->user, 'sanctum')
        ->patchJson('/api/issues/THI-1', ['title' => 'Still works'])
        ->assertOk();

    expect($this->one->fresh()->title)->toBe('Still works');
});

it('works from the web route too', function () {
    $this->actingAs($this->user)
        ->patch('/issues/bulk', ['issues' => ['THI-1', 'THI-2'], 'status' => 'done'])
        ->assertRedirect();

    expect($this->one->fresh()->status)->toBe(IssueStatus::Done);
});

it('is closed to anyone not signed in', function () {
    $this->patchJson('/api/issues/bulk', ['issues' => ['THI-1'], 'status' => 'done'])
        ->assertUnauthorized();
});

it('is closed to a service account', function () {
    $account = User::factory()->create(['is_service' => true]);

    Sanctum::actingAs($account, ['issues:create', 'issues:read']);

    $this->patchJson('/api/issues/bulk', ['issues' => ['THI-1'], 'status' => 'done'])
        ->assertForbidden();
});
