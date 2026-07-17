<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every issue predating the owner column was filed by the founding account
     * in the single-user era, so credit it as the reporter. Guarded on that
     * account existing, so fresh and test databases no-op.
     */
    public function up(): void
    {
        $owner = DB::table('users')->where('email', 'robbin_thijssen@hotmail.nl')->first();

        if ($owner === null) {
            return;
        }

        DB::table('issues')->whereNull('owner_id')->update(['owner_id' => $owner->id]);
    }

    public function down(): void
    {
        DB::table('issues')->update(['owner_id' => null]);
    }
};
