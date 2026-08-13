<?php

declare(strict_types=1);

use App\Actions\ReorderIssueAction;
use App\Enums\ProjectLevel;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\WorkflowState;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->type = ProjectType::factory()->create();
    $this->lane = WorkflowState::factory()->for($this->type, 'projectType')->create([
        'name' => 'Backlog', 'category' => StatusCategory::Backlog, 'position' => 1, 'is_default' => true,
    ]);
    $this->other = WorkflowState::factory()->for($this->type, 'projectType')->create([
        'name' => 'Doing', 'category' => StatusCategory::Started, 'position' => 2,
    ]);
    $this->project = Project::factory()->create(['key' => 'THI', 'project_type_id' => $this->type->id]);
    $this->user = member($this->project);
});

function laneOrder(Project $project, WorkflowState $lane): array
{
    return Issue::query()
        ->where('project_id', $project->id)
        ->where('workflow_state_id', $lane->id)
        ->orderBy('board_position')
        ->pluck('identifier')
        ->all();
}

function placed(Project $project, WorkflowState $lane, string $identifier, int $position): Issue
{
    $issue = Issue::factory()->for($project)->create([
        'identifier' => $identifier,
        'number' => (int) filter_var($identifier, FILTER_SANITIZE_NUMBER_INT),
        'workflow_state_id' => $lane->id,
    ]);

    DB::table('issues')->where('id', $issue->id)->update(['board_position' => $position]);

    return $issue->refresh();
}

it('drops an issue between two others', function () {
    placed($this->project, $this->lane, 'THI-1', 1000);
    $mover = placed($this->project, $this->lane, 'THI-2', 2000);
    $anchor = placed($this->project, $this->lane, 'THI-3', 3000);

    app(ReorderIssueAction::class)->handle($mover, $anchor->id);

    expect(laneOrder($this->project, $this->lane))->toBe(['THI-1', 'THI-3', 'THI-2']);
});

it('moves an issue to the top when no anchor is given', function () {
    placed($this->project, $this->lane, 'THI-1', 1000);
    $mover = placed($this->project, $this->lane, 'THI-2', 2000);

    app(ReorderIssueAction::class)->handle($mover, null);

    expect(laneOrder($this->project, $this->lane))->toBe(['THI-2', 'THI-1']);
});

it('writes only the moved row, leaving neighbours untouched', function () {
    $first = placed($this->project, $this->lane, 'THI-1', 1000);
    $mover = placed($this->project, $this->lane, 'THI-2', 2000);
    $anchor = placed($this->project, $this->lane, 'THI-3', 3000);

    app(ReorderIssueAction::class)->handle($mover, $anchor->id);

    expect($first->fresh()->board_position)->toBe(1000)
        ->and($anchor->fresh()->board_position)->toBe(3000);
});

it('rebalances when the insertion point itself has closed up', function () {
    // Dropping between 1000 and 1001 leaves nowhere to sit, so the lane is
    // spread out again rather than the drop failing to land.
    $anchor = placed($this->project, $this->lane, 'THI-1', 1000);
    placed($this->project, $this->lane, 'THI-2', 1001);
    $mover = placed($this->project, $this->lane, 'THI-3', 5000);

    app(ReorderIssueAction::class)->handle($mover, $anchor->id);

    expect(laneOrder($this->project, $this->lane))->toBe(['THI-1', 'THI-3', 'THI-2']);

    $positions = Issue::query()->orderBy('board_position')->pluck('board_position')->all();
    expect($positions[1] - $positions[0])->toBeGreaterThan(1)
        ->and($positions[2] - $positions[1])->toBeGreaterThan(1);
});

it('leaves a tight pair alone when the drop does not go between them', function () {
    // Only the insertion point matters. Rebalancing the whole lane because two
    // unrelated rows happen to be close would be wasted writes.
    placed($this->project, $this->lane, 'THI-1', 1000);
    $anchor = placed($this->project, $this->lane, 'THI-2', 1001);
    $mover = placed($this->project, $this->lane, 'THI-3', 5000);

    app(ReorderIssueAction::class)->handle($mover, $anchor->id);

    expect(laneOrder($this->project, $this->lane))->toBe(['THI-1', 'THI-2', 'THI-3'])
        ->and(Issue::query()->where('identifier', 'THI-1')->value('board_position'))->toBe(1000);
});

it('only reorders within the issue own lane', function () {
    placed($this->project, $this->lane, 'THI-1', 1000);
    $elsewhere = placed($this->project, $this->other, 'THI-2', 1000);
    $mover = placed($this->project, $this->lane, 'THI-3', 2000);

    app(ReorderIssueAction::class)->handle($mover, $elsewhere->id);

    // The anchor is in another lane, so it is not a valid neighbour: the mover
    // goes to the default rather than interleaving across lanes.
    expect(laneOrder($this->project, $this->other))->toBe(['THI-2']);
});

it('puts a newly filed issue at the top of its lane', function () {
    $older = placed($this->project, $this->lane, 'THI-1', 1000);

    $new = Issue::factory()->for($this->project)->create([
        'identifier' => 'THI-2',
        'number' => 2,
        'workflow_state_id' => $this->lane->id,
    ]);

    expect($new->fresh()->board_position)->toBeLessThan($older->fresh()->board_position);
});

it('reorders over the web route', function () {
    placed($this->project, $this->lane, 'THI-1', 1000);
    $mover = placed($this->project, $this->lane, 'THI-2', 2000);
    $anchor = placed($this->project, $this->lane, 'THI-3', 3000);

    $this->actingAs($this->user)
        ->patch("/issues/{$mover->identifier}/reorder", ['after' => $anchor->identifier])
        ->assertRedirect();

    expect(laneOrder($this->project, $this->lane))->toBe(['THI-1', 'THI-3', 'THI-2']);
});

it('takes write access to reorder', function () {
    $reader = member($this->project, ProjectLevel::Read);
    $mover = placed($this->project, $this->lane, 'THI-1', 1000);

    $this->actingAs($reader)
        ->patch("/issues/{$mover->identifier}/reorder", ['after' => null])
        ->assertForbidden();
});

it('will not anchor against an issue the actor cannot see', function () {
    $hidden = Issue::factory()->for(Project::factory()->create(['key' => 'SECRET']))->create(['identifier' => 'SECRET-1']);
    $mover = placed($this->project, $this->lane, 'THI-1', 1000);

    $this->actingAs($this->user)
        ->patch("/issues/{$mover->identifier}/reorder", ['after' => $hidden->identifier])
        ->assertForbidden();
});

it('lands where it was dropped when moving between lanes', function () {
    placed($this->project, $this->other, 'THI-1', 1000);
    $anchor = placed($this->project, $this->other, 'THI-2', 2000);
    $mover = placed($this->project, $this->lane, 'THI-3', 1000);

    $this->actingAs($this->user)
        ->patch("/issues/{$mover->identifier}/state", [
            'workflow_state_id' => $this->other->id,
            'after' => $anchor->identifier,
        ])
        ->assertRedirect();

    expect(laneOrder($this->project, $this->other))->toBe(['THI-1', 'THI-2', 'THI-3']);
});

it('goes to the top of the new lane when dropped without an anchor', function () {
    placed($this->project, $this->other, 'THI-1', 1000);
    $mover = placed($this->project, $this->lane, 'THI-2', 5000);

    $this->actingAs($this->user)
        ->patch("/issues/{$mover->identifier}/state", ['workflow_state_id' => $this->other->id])
        ->assertRedirect();

    expect(laneOrder($this->project, $this->other))->toBe(['THI-2', 'THI-1']);
});

it('orders the board by position', function () {
    placed($this->project, $this->lane, 'THI-1', 3000);
    placed($this->project, $this->lane, 'THI-2', 1000);

    $this->actingAs($this->user)
        ->get('/issues/board')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('issues.0.identifier', 'THI-2'));
});

it('backfills positions from the order the board already showed', function () {
    $older = Issue::factory()->for($this->project)->create(['identifier' => 'THI-1', 'number' => 1, 'workflow_state_id' => $this->lane->id]);
    $newer = Issue::factory()->for($this->project)->create(['identifier' => 'THI-2', 'number' => 2, 'workflow_state_id' => $this->lane->id]);
    DB::table('issues')->update(['board_position' => 0]);
    DB::table('issues')->where('id', $older->id)->update(['created_at' => now()->subDay()]);

    (require database_path('migrations/2026_08_08_100100_backfill_board_positions.php'))->up();

    // Newest first, matching the old latest() ordering.
    expect(laneOrder($this->project, $this->lane))->toBe(['THI-2', 'THI-1']);
});

it('leaves an already positioned board alone', function () {
    placed($this->project, $this->lane, 'THI-1', 7000);

    (require database_path('migrations/2026_08_08_100100_backfill_board_positions.php'))->up();

    expect(Issue::query()->first()->board_position)->toBe(7000);
});
