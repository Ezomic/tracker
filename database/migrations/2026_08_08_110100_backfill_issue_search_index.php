<?php

use App\Models\Issue;
use App\Support\IssueSearch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fills the index for issues that already exist. Guarded: it no-ops when there
 * is no index (a non-SQLite database) or when it is already populated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! IssueSearch::available() || DB::table('issue_search')->count() > 0) {
            return;
        }

        Issue::query()->with('comments:id,issue_id,body')->chunkById(200, function ($issues): void {
            foreach ($issues as $issue) {
                IssueSearch::index($issue);
            }
        });
    }

    public function down(): void
    {
        if (IssueSearch::available()) {
            DB::table('issue_search')->delete();
        }
    }
};
