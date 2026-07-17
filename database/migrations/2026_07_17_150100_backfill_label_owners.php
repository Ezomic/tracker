<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every label predating the owner column was created by the founding
     * account in the single-user era. Guarded on that account existing, so
     * fresh and test databases no-op.
     */
    public function up(): void
    {
        $owner = DB::table('users')->where('email', 'robbin_thijssen@hotmail.nl')->first();

        if ($owner === null) {
            return;
        }

        DB::table('labels')->whereNull('user_id')->update(['user_id' => $owner->id]);
    }

    public function down(): void
    {
        DB::table('labels')->update(['user_id' => null]);
    }
};
