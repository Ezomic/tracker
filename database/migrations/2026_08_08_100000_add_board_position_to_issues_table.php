<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            // Sparse on purpose: gaps of 1000 mean a reorder writes one row
            // rather than renumbering the whole lane.
            $table->integer('board_position')->default(0)->after('workflow_state_id');
            $table->index(['workflow_state_id', 'board_position']);
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropIndex(['workflow_state_id', 'board_position']);
            $table->dropColumn('board_position');
        });
    }
};
