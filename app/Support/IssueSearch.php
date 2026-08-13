<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Comment;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ranked search over titles, descriptions and comment threads.
 *
 * The index is FTS5 and therefore SQLite-only. Everywhere else, and on a
 * database where the migration has not run, this falls back to the LIKE
 * behaviour that came before, so search never simply stops working.
 */
final class IssueSearch
{
    public static function available(): bool
    {
        return DB::getDriverName() === 'sqlite' && Schema::hasTable('issue_search');
    }

    /**
     * Apply a search term, ranked when the index is available.
     *
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    public static function apply(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        if (! self::available()) {
            return self::like($query, $term);
        }

        $ids = self::matchingIds($term);

        if ($ids === []) {
            // An empty match must return nothing, not everything.
            return $query->whereRaw('1 = 0');
        }

        // Ordered by the index's own relevance. The CASE is built from
        // placeholders rather than interpolation, so the ids are bound.
        $cases = implode(' ', array_fill(0, count($ids), 'WHEN ? THEN ?'));

        $bindings = [];

        foreach ($ids as $position => $id) {
            $bindings[] = $id;
            $bindings[] = $position;
        }

        return $query
            ->whereIn('issues.id', $ids)
            ->orderByRaw("CASE issues.id {$cases} END", $bindings);
    }

    /**
     * @return list<int>
     */
    private static function matchingIds(string $term): array
    {
        $expression = self::expression($term);

        if ($expression === '') {
            return [];
        }

        try {
            $rows = DB::select(
                'select issue_id from issue_search where issue_search match ? order by bm25(issue_search, 0.0, 10.0, 5.0, 2.0, 1.0) limit 500',
                [$expression],
            );
        } catch (\Throwable) {
            // A malformed FTS expression is a user typing, not an outage.
            return [];
        }

        $ids = [];

        foreach ($rows as $row) {
            $ids[] = is_object($row) && property_exists($row, 'issue_id')
                ? Cast::int($row->issue_id)
                : 0;
        }

        return array_values(array_filter($ids, fn (int $id): bool => $id > 0));
    }

    /**
     * Multi-word queries are AND across terms with a trailing prefix match, so
     * "invoice discount" finds an issue about a discount on invoices rather
     * than only that exact adjacent string.
     *
     * Everything is quoted, so a stray quote or operator is searched for
     * rather than changing the query's meaning.
     */
    private static function expression(string $term): string
    {
        $words = preg_split('/\s+/', $term) ?: [];

        $quoted = [];

        foreach ($words as $word) {
            $clean = preg_replace('/["*]/', '', $word) ?? '';

            if ($clean !== '') {
                $quoted[] = '"'.$clean.'"*';
            }
        }

        return implode(' AND ', $quoted);
    }

    /**
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    private static function like(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $group) => $group
            ->where('title', 'like', $like)
            ->orWhere('identifier', 'like', $like)
            ->orWhere('description', 'like', $like));
    }

    /**
     * Rewrite one issue's row in the index. Cheap enough to do inline on save;
     * the alternative is triggers, which are harder to keep in step with the
     * comment table.
     */
    public static function index(Issue $issue): void
    {
        if (! self::available()) {
            return;
        }

        DB::table('issue_search')->where('issue_id', $issue->id)->delete();

        DB::table('issue_search')->insert([
            'issue_id' => $issue->id,
            'identifier' => $issue->identifier,
            'title' => $issue->title,
            'description' => $issue->description ?? '',
            'comments' => $issue->comments()->pluck('body')->implode(' '),
        ]);
    }

    public static function forget(Issue $issue): void
    {
        if (! self::available()) {
            return;
        }

        DB::table('issue_search')->where('issue_id', $issue->id)->delete();
    }

    /**
     * Reindex the issue a comment belongs to, so the thread is searchable.
     */
    public static function indexFor(Comment $comment): void
    {
        $issue = $comment->issue;

        if ($issue instanceof Issue) {
            self::index($issue);
        }
    }
}
