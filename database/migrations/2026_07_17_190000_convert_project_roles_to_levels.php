<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Project membership becomes a grant level rather than a role. The project
     * "owner" concept is gone (the organization owns projects), so owner and
     * admin both collapse to admin; member becomes write.
     */
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->renameColumn('role', 'level');
        });

        DB::table('project_user')->whereIn('level', ['owner', 'admin'])->update(['level' => 'admin']);
        DB::table('project_user')->where('level', 'member')->update(['level' => 'write']);

        Schema::table('project_user', function (Blueprint $table) {
            $table->string('level')->default('write')->change();
        });

        // Pending invitations carry the level they will grant on acceptance.
        Schema::table('invitations', function (Blueprint $table) {
            $table->renameColumn('role', 'level');
        });

        DB::table('invitations')->where('level', 'member')->update(['level' => 'write']);
    }

    public function down(): void
    {
        DB::table('project_user')->where('level', 'admin')->update(['level' => 'owner']);
        DB::table('project_user')->where('level', 'write')->update(['level' => 'member']);
        DB::table('project_user')->where('level', 'read')->update(['level' => 'member']);

        Schema::table('project_user', function (Blueprint $table) {
            $table->renameColumn('level', 'role');
        });

        DB::table('invitations')->where('level', 'write')->update(['level' => 'member']);
        DB::table('invitations')->where('level', 'read')->update(['level' => 'member']);

        Schema::table('invitations', function (Blueprint $table) {
            $table->renameColumn('level', 'role');
        });
    }
};
