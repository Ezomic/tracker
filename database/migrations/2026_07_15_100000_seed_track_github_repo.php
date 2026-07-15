<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')
            ->where('key', 'TRACK')
            ->update(['github_repo' => 'Ezomic/tracker']);
    }

    public function down(): void
    {
        DB::table('projects')
            ->where('key', 'TRACK')
            ->update(['github_repo' => null]);
    }
};
