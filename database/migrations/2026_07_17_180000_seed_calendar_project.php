<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FOUNDER_EMAIL = 'robbin_thijssen@hotmail.nl';

    private const KEY = 'CAL';

    /**
     * Seed a tracker project for the new Calendar app. There is no
     * project-creation API and no shell on the box, so this is the channel for
     * it. Guarded on the founder existing and the key being free, so it is
     * idempotent and no-ops on fresh or test databases.
     */
    public function up(): void
    {
        $founder = DB::table('users')->where('email', self::FOUNDER_EMAIL)->first();

        if ($founder === null || DB::table('projects')->where('key', self::KEY)->exists()) {
            return;
        }

        // The founder's own organization (Thijssen Software after phase 1).
        $organizationId = DB::table('organization_user')
            ->where('user_id', $founder->id)
            ->where('role', OrganizationRole::Owner->value)
            ->value('organization_id');

        $now = now();

        $projectId = DB::table('projects')->insertGetId([
            'organization_id' => $organizationId,
            'key' => self::KEY,
            'name' => 'Calendar',
            'color' => '#0e9aa7',
            'next_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('project_user')->insert([
            'project_id' => $projectId,
            'user_id' => $founder->id,
            'role' => ProjectRole::Owner->value,
            'is_favorite' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('projects')->where('key', self::KEY)->delete();
    }
};
