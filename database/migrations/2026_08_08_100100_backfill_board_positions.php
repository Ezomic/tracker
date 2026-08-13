<?php

use App\Support\Cast;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds positions from the order the board already showed, newest first, so
 * nothing visibly reshuffles on deploy. Guarded: a database with no issues, or
 * one already positioned, no-ops.
 */
return new class extends Migration
{
    private const GAP = 1000;

    public function up(): void
    {
        if (! DB::table('issues')->where('board_position', 0)->exists()) {
            return;
        }

        $lanes = DB::table('issues')
            ->select('workflow_state_id')
            ->distinct()
            ->pluck('workflow_state_id');

        foreach ($lanes as $lane) {
            $query = DB::table('issues');

            $lane === null
                ? $query->whereNull('workflow_state_id')
                : $query->where('workflow_state_id', Cast::int($lane));

            $position = self::GAP;

            foreach ($query->orderByDesc('created_at')->orderByDesc('id')->pluck('id') as $id) {
                DB::table('issues')->where('id', Cast::int($id))->update(['board_position' => $position]);
                $position += self::GAP;
            }
        }
    }

    public function down(): void
    {
        // Positions derived from an ordering that still exists; nothing to restore.
    }
};
