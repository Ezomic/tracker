<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make the founding account the Owner of every existing project so nothing
     * loses access once queries become membership-scoped, copying each project's
     * current global favorite into the owner's pivot row. Guarded on the account
     * existing, so a fresh or test database no-ops.
     */
    public function up(): void
    {
        $owner = DB::table('users')->where('email', 'robbin_thijssen@hotmail.nl')->first();

        if ($owner === null) {
            return;
        }

        $projects = DB::table('projects')->get(['id', 'is_favorite']);
        $now = now();

        foreach ($projects as $project) {
            DB::table('project_user')->updateOrInsert(
                ['project_id' => $project->id, 'user_id' => $owner->id],
                [
                    'role' => 'owner',
                    'is_favorite' => (bool) $project->is_favorite,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $owner = DB::table('users')->where('email', 'robbin_thijssen@hotmail.nl')->first();

        if ($owner === null) {
            return;
        }

        DB::table('project_user')->where('user_id', $owner->id)->delete();
    }
};
