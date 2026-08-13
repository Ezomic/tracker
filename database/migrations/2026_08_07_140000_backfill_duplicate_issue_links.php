<?php

use App\Enums\IssueRelation;
use App\Support\Cast;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Turns the duplicate relationships already recorded as prose into real links.
 *
 * 2026_07_20_180000_record_duplicate_archive_reasons wrote
 * "Duplicate of X, which covers the same work." into archive_reason. That is
 * the relationship this feature exists to model, and it is already sitting in
 * the database as text nothing can query.
 *
 * The text is left alone: it is the audit trail of why the issue was archived,
 * and the link is the queryable form of the same fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('issues')
            ->whereNotNull('archive_reason')
            ->where('archive_reason', 'like', 'Duplicate of %')
            ->get(['id', 'archive_reason']);

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $row) {
            if (preg_match('/^Duplicate of ([A-Z][A-Z0-9]*-\d+)/', Cast::string($row->archive_reason), $matches) !== 1) {
                continue;
            }

            $target = DB::table('issues')->where('identifier', $matches[1])->first(['id']);

            if ($target === null) {
                continue;
            }

            $this->link(Cast::int($row->id), Cast::int($target->id), IssueRelation::Duplicates);
            $this->link(Cast::int($target->id), Cast::int($row->id), IssueRelation::DuplicatedBy);
        }
    }

    public function down(): void
    {
        // The prose these were derived from is untouched, so nothing is lost by
        // leaving the links in place.
    }

    private function link(int $from, int $to, IssueRelation $relation): void
    {
        $exists = DB::table('issue_links')
            ->where('issue_id', $from)
            ->where('related_issue_id', $to)
            ->where('relation', $relation->value)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('issue_links')->insert([
            'issue_id' => $from,
            'related_issue_id' => $to,
            'relation' => $relation->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
