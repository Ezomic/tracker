<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A full-text index over what people actually search for: the title, the
 * description, and the comment thread, where the decision and the reproduction
 * steps usually live.
 *
 * SQLite-only. Anything else keeps the LIKE fallback, which is why the app
 * checks for the table rather than assuming it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('CREATE VIRTUAL TABLE IF NOT EXISTS issue_search USING fts5(
            issue_id UNINDEXED,
            identifier,
            title,
            description,
            comments,
            tokenize = "unicode61"
        )');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::dropIfExists('issue_search');
        }
    }
};
