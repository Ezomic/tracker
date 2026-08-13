<?php

declare(strict_types=1);

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Support\Staleness;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
});

/**
 * Build an issue whose last activity is a given number of days ago. The
 * observer stamps a `created` activity on save, so that row is moved rather
 * than a second one added.
 */
function quietFor(Project $project, int $days, array $attributes = []): Issue
{
    $issue = Issue::factory()->for($project)->create($attributes);

    DB::table('activities')->where('issue_id', $issue->id)->update([
        'created_at' => now()->subDays($days),
    ]);
    DB::table('issues')->where('id', $issue->id)->update([
        'created_at' => now()->subDays($days),
    ]);

    return $issue->refresh();
}

it('finds an issue nobody has touched past the default threshold', function () {
    $stale = quietFor($this->project, 45, ['identifier' => 'THI-1']);
    quietFor($this->project, 5, ['identifier' => 'THI-2']);

    $found = Staleness::scope(Issue::query())->pluck('identifier')->all();

    expect($found)->toBe([$stale->identifier]);
});

it('measures from the last activity, not from creation', function () {
    $issue = quietFor($this->project, 90, ['identifier' => 'THI-1']);
    // Filed long ago but commented on yesterday: not forgotten.
    $issue->recordActivity('status_changed');
    DB::table('activities')->where('issue_id', $issue->id)->orderByDesc('id')->limit(1)->update([
        'created_at' => now()->subDay(),
    ]);

    expect(Staleness::scope(Issue::query())->count())->toBe(0);
});

it('honours a per-project threshold over the default', function () {
    $this->project->forceFill(['stale_after_days' => 7])->save();
    quietFor($this->project, 10, ['identifier' => 'THI-1']);

    expect(Staleness::scope(Issue::query())->count())->toBe(1);
});

it('falls back to the default when a project sets none', function () {
    expect($this->project->stale_after_days)->toBeNull()
        ->and(Staleness::daysFor($this->project))->toBe(30);
});

it('ignores done issues, which auto-archive already handles', function () {
    quietFor($this->project, 60, ['identifier' => 'THI-1', 'status' => IssueStatus::Done]);

    expect(Staleness::scope(Issue::query())->count())->toBe(0);
});

it('ignores archived issues', function () {
    quietFor($this->project, 60, ['identifier' => 'THI-1', 'archived_at' => now()]);

    expect(Staleness::scope(Issue::query())->count())->toBe(0);
});

it('puts stale issues on the dashboard, quietest first', function () {
    quietFor($this->project, 40, ['identifier' => 'THI-1']);
    quietFor($this->project, 90, ['identifier' => 'THI-2']);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('stale', 2)
            ->where('stale.0.identifier', 'THI-2')
            ->where('stale.0.quietDays', fn (int $d): bool => $d >= 89));
});

it('leaves the dashboard section empty when nothing is stale', function () {
    quietFor($this->project, 2, ['identifier' => 'THI-1']);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('stale', 0));
});

it('filters the API list', function () {
    quietFor($this->project, 60, ['number' => 1, 'identifier' => 'THI-1']);
    quietFor($this->project, 1, ['number' => 2, 'identifier' => 'THI-2']);

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues?stale=1');

    expect($response->json('data.*.identifier'))->toBe(['THI-1']);
});

it('returns everything when the API filter is off', function () {
    quietFor($this->project, 60, ['number' => 1, 'identifier' => 'THI-1']);
    quietFor($this->project, 1, ['number' => 2, 'identifier' => 'THI-2']);

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues');

    expect($response->json('meta.total'))->toBe(2);
});

it('filters the web list', function () {
    quietFor($this->project, 60, ['number' => 1, 'identifier' => 'THI-1']);
    quietFor($this->project, 1, ['number' => 2, 'identifier' => 'THI-2']);

    $this->actingAs($this->user)
        ->get('/issues?stale=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('issues', 1)->where('issues.0.identifier', 'THI-1'));
});

it('never archives or notifies, it only surfaces', function () {
    $issue = quietFor($this->project, 200, ['identifier' => 'THI-1']);

    $this->actingAs($this->user)->get('/dashboard')->assertOk();

    expect($issue->fresh()->archived_at)->toBeNull()
        ->and($issue->fresh()->status)->not->toBe(IssueStatus::Done);
});

it('lets a project set its own threshold through the API', function () {
    $this->actingAs($this->user, 'sanctum')
        ->patchJson('/api/projects/THI', ['key' => 'THI', 'name' => $this->project->name, 'stale_after_days' => 14])
        ->assertOk();

    expect($this->project->fresh()->stale_after_days)->toBe(14);
});

it('rejects a nonsense threshold', function () {
    $this->actingAs($this->user, 'sanctum')
        ->patchJson('/api/projects/THI', ['key' => 'THI', 'name' => $this->project->name, 'stale_after_days' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stale_after_days');
});
