<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // A service account is a User so that project membership, visibleTo
            // and the policies apply to it unchanged. The flag is what stops it
            // ever holding a session.
            $table->boolean('is_service')->default(false)->after('email_verified_at');
        });

        Schema::table('issues', function (Blueprint $table): void {
            // Who reported it, when that person has no account here: a name or,
            // for arbo, an HMAC pseudonym the host app computed.
            $table->string('external_reporter')->nullable()->after('external_ref');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_service');
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn('external_reporter');
        });
    }
};
