<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Done issues used to auto-archive after one day by default, which is too quick
 * to review what just shipped. New projects now start at a week, and every
 * project still on the old one-day default moves to a week too. Projects set to
 * anything else (14, 30, a custom value, or never) are left alone. Guarded: a
 * database with no one-day projects no-ops.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedInteger('archive_after_days')->nullable()->default(7)->change();
        });

        if (! DB::table('projects')->where('archive_after_days', 1)->exists()) {
            return;
        }

        DB::table('projects')->where('archive_after_days', 1)->update(['archive_after_days' => 7]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedInteger('archive_after_days')->nullable()->default(1)->change();
        });
    }
};
