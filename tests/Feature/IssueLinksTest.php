<?php

declare(strict_types=1);

use App\Actions\LinkIssuesAction;
use App\Enums\IssueRelation;
use App\Enums\ProjectLevel;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
    $this->issue = Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1']);
    $this->other = Issue::factory()->for($this->project)->create(['number' => 2, 'identifier' => 'THI-2']);
});

it('writes both directions of a link', function () {
    $this->actingAs($this->user)
        ->post('/issues/THI-1/links', ['issue' => 'THI-2', 'relation' => 'blocks'])
        ->assertRedirect();

    expect($this->issue->links()->first()->relation)->toBe(IssueRelation::Blocks)
        ->and($this->other->links()->first()->relation)->toBe(IssueRelation::BlockedBy);
});

it('makes relates_to symmetric', function () {
    app(LinkIssuesAction::class)->handle($this->issue, $this->other, IssueRelation::RelatesTo);

    expect($this->issue->links()->first()->relation)->toBe(IssueRelation::RelatesTo)
        ->and($this->other->links()->first()->relation)->toBe(IssueRelation::RelatesTo);
});

it('pairs duplicates with duplicated by', function () {
    app(LinkIssuesAction::class)->handle($this->issue, $this->other, IssueRelation::Duplicates);

    expect($this->other->links()->first()->relation)->toBe(IssueRelation::DuplicatedBy);
});

it('is idempotent', function () {
    app(LinkIssuesAction::class)->handle($this->issue, $this->other, IssueRelation::Blocks);
    app(LinkIssuesAction::class)->handle($this->issue, $this->other, IssueRelation::Blocks);

    expect(IssueLink::query()->count())->toBe(2);
});

it('removes both directions on unlink', function () {
    app(LinkIssuesAction::class)->handle($this->issue, $this->other, IssueRelation::Blocks);
    $link = $this->issue->links()->firstOrFail();

    $this->actingAs($this->user)
        ->delete("/issues/THI-1/links/{$link->id}")
        ->assertRedirect();

    expect(IssueLink::query()->count())->toBe(0);
});

it('refuses to link an issue to itself', function () {
    $this->actingAs($this->user)
        ->post('/issues/THI-1/links', ['issue' => 'THI-1', 'relation' => 'blocks'])
        ->assertSessionHasErrors('issue');

    expect(IssueLink::query()->count())->toBe(0);
});

it('refuses an issue that does not exist', function () {
    $this->actingAs($this->user)
        ->post('/issues/THI-1/links', ['issue' => 'NOPE-9', 'relation' => 'blocks'])
        ->assertSessionHasErrors('issue');
});

it('refuses an inverse relation as a direct choice', function () {
    // You say "blocks"; the other side gets "blocked by". Choosing the inverse
    // directly would write the pair backwards.
    $this->actingAs($this->user)
        ->post('/issues/THI-1/links', ['issue' => 'THI-2', 'relation' => 'duplicated_by'])
        ->assertSessionHasErrors('relation');
});

it('accepts blocked_by, which is a real direction to choose', function () {
    $this->actingAs($this->user)
        ->post('/issues/THI-1/links', ['issue' => 'THI-2', 'relation' => 'blocked_by'])
        ->assertRedirect();

    expect($this->issue->links()->first()->relation)->toBe(IssueRelation::BlockedBy)
        ->and($this->other->links()->first()->relation)->toBe(IssueRelation::Blocks);
});

it('will not link to an issue in a project the actor cannot see', function () {
    $hidden = Issue::factory()->for(Project::factory()->create(['key' => 'SECRET']))->create(['identifier' => 'SECRET-1']);

    $this->actingAs($this->user)
        ->post('/issues/THI-1/links', ['issue' => 'SECRET-1', 'relation' => 'relates_to'])
        ->assertForbidden();

    expect(IssueLink::query()->count())->toBe(0);
});

it('takes write access to link', function () {
    $reader = member($this->project, ProjectLevel::Read);

    $this->actingAs($reader)
        ->post('/issues/THI-1/links', ['issue' => 'THI-2', 'relation' => 'blocks'])
        ->assertForbidden();
});

it('records the link on the timeline', function () {
    $this->actingAs($this->user)->post('/issues/THI-1/links', ['issue' => 'THI-2', 'relation' => 'blocks']);

    expect($this->issue->activities()->where('type', 'linked')->exists())->toBeTrue();
});

it('drops links when either issue is deleted', function () {
    app(LinkIssuesAction::class)->handle($this->issue, $this->other, IssueRelation::Blocks);

    $this->other->delete();

    expect(IssueLink::query()->count())->toBe(0);
});

it('shows links on the issue page with the target status', function () {
    app(LinkIssuesAction::class)->handle($this->issue, $this->other, IssueRelation::Blocks);

    $this->actingAs($this->user)
        ->get('/issues/THI-1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('issue.links', 1)
            ->where('issue.links.0.relation', 'blocks')
            ->where('issue.links.0.issue.identifier', 'THI-2')
            ->where('issue.links.0.issue.status', $this->other->status->value));
});

it('links over the API', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/issues/THI-1/links', ['issue' => 'THI-2', 'relation' => 'duplicates']);

    $response->assertCreated();
    expect($response->json('links'))->toBe([['relation' => 'duplicates', 'issue' => 'THI-2']]);
});

it('unlinks over the API', function () {
    app(LinkIssuesAction::class)->handle($this->issue, $this->other, IssueRelation::Blocks);
    $link = $this->issue->links()->firstOrFail();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/issues/THI-1/links/{$link->id}")
        ->assertNoContent();

    expect(IssueLink::query()->count())->toBe(0);
});

it('refuses a link id belonging to another issue', function () {
    app(LinkIssuesAction::class)->handle($this->issue, $this->other, IssueRelation::Blocks);
    $theirs = $this->other->links()->firstOrFail();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/issues/THI-1/links/{$theirs->id}")
        ->assertNotFound();
});

it('backfills the duplicates already recorded as prose', function () {
    DB::table('issues')->where('id', $this->issue->id)->update([
        'archived_at' => now(),
        'archive_reason' => 'Duplicate of THI-2, which covers the same work.',
    ]);

    (require database_path('migrations/2026_08_07_140000_backfill_duplicate_issue_links.php'))->up();

    expect($this->issue->links()->first()->relation)->toBe(IssueRelation::Duplicates)
        ->and($this->other->links()->first()->relation)->toBe(IssueRelation::DuplicatedBy)
        // The prose stays: it is the audit trail of why it was archived.
        ->and($this->issue->fresh()->archive_reason)->toBe('Duplicate of THI-2, which covers the same work.');
});

it('leaves an unparseable or dangling reason alone', function () {
    DB::table('issues')->where('id', $this->issue->id)->update([
        'archived_at' => now(),
        'archive_reason' => 'Duplicate of GONE-99, which covers the same work.',
    ]);

    (require database_path('migrations/2026_08_07_140000_backfill_duplicate_issue_links.php'))->up();

    expect(IssueLink::query()->count())->toBe(0);
});

it('backfills idempotently', function () {
    DB::table('issues')->where('id', $this->issue->id)->update([
        'archived_at' => now(),
        'archive_reason' => 'Duplicate of THI-2, which covers the same work.',
    ]);

    $migration = require database_path('migrations/2026_08_07_140000_backfill_duplicate_issue_links.php');
    $migration->up();
    $migration->up();

    expect(IssueLink::query()->count())->toBe(2);
});
