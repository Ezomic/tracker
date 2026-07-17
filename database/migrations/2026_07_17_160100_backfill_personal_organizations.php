<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const FOUNDER_EMAIL = 'robbin_thijssen@hotmail.nl';

    /**
     * Give every existing user a personal organization and file each project
     * under its owner's org, so nothing is orphaned once projects live under an
     * organization. Guarded on there being users, so a fresh database no-ops.
     */
    public function up(): void
    {
        $users = DB::table('users')->get(['id', 'name', 'email']);
        $now = now();
        $orgIdByUser = [];

        foreach ($users as $user) {
            // The founder's org is the real company; everyone else gets one
            // named after them.
            $name = $user->email === self::FOUNDER_EMAIL
                ? 'Thijssen Software'
                : $user->name;

            $orgId = DB::table('organizations')->insertGetId([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('organization_user')->insert([
                'organization_id' => $orgId,
                'user_id' => $user->id,
                'role' => OrganizationRole::Owner->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $orgIdByUser[$user->id] = $orgId;
        }

        $ownerships = DB::table('project_user')
            ->where('role', 'owner')
            ->get(['project_id', 'user_id']);

        foreach ($ownerships as $ownership) {
            if (! isset($orgIdByUser[$ownership->user_id])) {
                continue;
            }

            DB::table('projects')
                ->where('id', $ownership->project_id)
                ->update(['organization_id' => $orgIdByUser[$ownership->user_id]]);
        }
    }

    public function down(): void
    {
        DB::table('projects')->update(['organization_id' => null]);
        DB::table('organization_user')->delete();
        DB::table('organizations')->delete();
    }
};
