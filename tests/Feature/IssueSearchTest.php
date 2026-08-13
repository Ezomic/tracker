<?php

declare(strict_types=1);

use App\Actions\AddCommentAction;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use App\Support\IssueSearch;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->project = Project::factory()->create(['key' => 'THI']);
    $this->user = member($this->project);
});

function found(User $user, string $term): array
{
    return IssueSearch::apply(Issue::query()->visibleTo($user), $term)
        ->pluck('identifier')
        ->all();
}

it('has the index available on sqlite', function () {
    expect(IssueSearch::available())->toBeTrue();
});

it('finds an issue by a word in its title', function () {
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget alignment is off']);
    Issue::factory()->for($this->project)->create(['number' => 2, 'identifier' => 'THI-2', 'title' => 'Unrelated']);

    expect(found($this->user, 'widget'))->toBe(['THI-1']);
});

it('finds an issue by its description', function () {
    Issue::factory()->for($this->project)->create([
        'number' => 1, 'identifier' => 'THI-1', 'title' => 'Something', 'description' => 'The invoice total is wrong',
    ]);

    expect(found($this->user, 'invoice'))->toBe(['THI-1']);
});

it('finds an issue by a word only in its comments', function () {
    $issue = Issue::factory()->for($this->project)->create([
        'number' => 1, 'identifier' => 'THI-1', 'title' => 'Something', 'description' => 'Nothing helpful',
    ]);

    app(AddCommentAction::class)->handle($issue, $this->user, 'Turns out it was the discount calculation');

    expect(found($this->user, 'discount'))->toBe(['THI-1']);
});

it('ranks a title match above a comment match', function () {
    $comment = Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Unrelated', 'description' => '']);
    app(AddCommentAction::class)->handle($comment, $this->user, 'mentions checkout in passing');

    Issue::factory()->for($this->project)->create(['number' => 2, 'identifier' => 'THI-2', 'title' => 'Checkout is broken', 'description' => '']);

    expect(found($this->user, 'checkout')[0])->toBe('THI-2');
});

it('treats multiple words as AND rather than one literal string', function () {
    Issue::factory()->for($this->project)->create([
        'number' => 1, 'identifier' => 'THI-1', 'title' => 'Discount applied to the invoice', 'description' => '',
    ]);
    Issue::factory()->for($this->project)->create([
        'number' => 2, 'identifier' => 'THI-2', 'title' => 'Invoice only', 'description' => '',
    ]);

    expect(found($this->user, 'invoice discount'))->toBe(['THI-1']);
});

it('matches a prefix, so a half-typed word still finds things', function () {
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Checkout crashes']);

    expect(found($this->user, 'check'))->toBe(['THI-1']);
});

it('finds an issue by identifier', function () {
    Issue::factory()->for($this->project)->create(['number' => 42, 'identifier' => 'THI-42', 'title' => 'Anything']);

    expect(found($this->user, 'THI-42'))->toBe(['THI-42']);
});

it('returns nothing rather than everything when nothing matches', function () {
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget']);

    expect(found($this->user, 'nonexistentterm'))->toBe([]);
});

it('treats a stray quote as text rather than letting it change the query', function () {
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget']);
    Issue::factory()->for($this->project)->create(['number' => 2, 'identifier' => 'THI-2', 'title' => 'Totally unrelated']);

    // If the quote escaped, OR would take effect as an operator and pull in
    // rows matching either side. Every term is quoted and ANDed, so it does
    // not: the terms are searched for literally and nothing matches.
    expect(found($this->user, 'widget" OR "unrelated'))->toBe([]);
});

it('survives an operator-only query without erroring', function () {
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget']);

    expect(found($this->user, '***'))->toBe([]);
});

it('keeps the index current when a title changes', function () {
    $issue = Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Before']);

    $issue->forceFill(['title' => 'Afterwards'])->save();

    expect(found($this->user, 'afterwards'))->toBe(['THI-1'])
        ->and(found($this->user, 'before'))->toBe([]);
});

it('keeps the index current when a comment is removed', function () {
    $issue = Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Something']);
    $comment = Comment::factory()->for($issue)->for($this->user, 'user')->create(['body' => 'transient detail']);

    expect(found($this->user, 'transient'))->toBe(['THI-1']);

    $comment->delete();

    expect(found($this->user, 'transient'))->toBe([]);
});

it('drops the index row when an issue is deleted', function () {
    $issue = Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget']);

    $issue->delete();

    expect(DB::table('issue_search')->count())->toBe(0);
});

it('never leaks an issue from a project the searcher cannot see', function () {
    Issue::factory()->for(Project::factory()->create(['key' => 'SECRET']))
        ->create(['identifier' => 'SECRET-1', 'title' => 'Widget alignment']);
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget alignment']);

    expect(found($this->user, 'widget'))->toBe(['THI-1']);
});

it('searches from the API list', function () {
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget alignment']);
    Issue::factory()->for($this->project)->create(['number' => 2, 'identifier' => 'THI-2', 'title' => 'Unrelated']);

    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/issues?search=widget');

    expect($response->json('data.*.identifier'))->toBe(['THI-1']);
});

it('searches from the command palette', function () {
    $issue = Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget alignment']);
    app(AddCommentAction::class)->handle($issue, $this->user, 'caused by rounding');

    $response = $this->actingAs($this->user)->getJson('/issues/search?q=rounding');

    $response->assertOk();
    expect($response->json('0.identifier'))->toBe('THI-1');
});

it('backfills existing issues into the index', function () {
    $issue = Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Historic widget']);
    DB::table('issue_search')->delete();

    (require database_path('migrations/2026_08_08_110100_backfill_issue_search_index.php'))->up();

    expect(found($this->user, 'historic'))->toBe(['THI-1']);
});

it('does not double up when the backfill runs twice', function () {
    Issue::factory()->for($this->project)->create(['number' => 1, 'identifier' => 'THI-1', 'title' => 'Widget']);

    $migration = require database_path('migrations/2026_08_08_110100_backfill_issue_search_index.php');
    $migration->up();
    $migration->up();

    expect(DB::table('issue_search')->where('issue_id', Issue::query()->value('id'))->count())->toBe(1);
});
