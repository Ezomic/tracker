<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // Null falls back to the app default rather than meaning "never",
            // unlike archive_after_days: an issue nobody has touched is worth
            // surfacing by default, whereas auto-archiving is opt-in.
            $table->unsignedSmallInteger('stale_after_days')->nullable()->after('archive_after_days');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('stale_after_days');
        });
    }
};
