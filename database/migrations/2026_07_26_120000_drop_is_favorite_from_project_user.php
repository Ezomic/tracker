<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Favorites are gone; the sidebar now groups projects by category. Drop the
     * per-user favorite flag from the pivot.
     */
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('is_favorite');
        });
    }

    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false);
        });
    }
};
