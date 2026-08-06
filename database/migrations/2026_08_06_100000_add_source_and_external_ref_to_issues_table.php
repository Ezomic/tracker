<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->string('source')->nullable()->after('template_id');
            $table->string('external_ref')->nullable()->after('source');

            // NULLs compare as distinct in a unique index, so this constrains
            // only the rows that actually carry an external reference; issues
            // filed by hand are unaffected.
            $table->unique(['project_id', 'source', 'external_ref'], 'issues_external_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropUnique('issues_external_ref_unique');
            $table->dropColumn(['source', 'external_ref']);
        });
    }
};
