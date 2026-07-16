<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('github_repos')->nullable()->after('color');
        });

        DB::table('projects')
            ->whereNotNull('github_repo')
            ->where('github_repo', '!=', '')
            ->orderBy('id')
            ->each(function (object $project) {
                DB::table('projects')
                    ->where('id', $project->id)
                    ->update(['github_repos' => json_encode([$project->github_repo])]);
            });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('github_repo');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('github_repo')->nullable()->after('color');
        });

        DB::table('projects')
            ->whereNotNull('github_repos')
            ->orderBy('id')
            ->each(function (object $project) {
                $repos = json_decode((string) $project->github_repos, true);

                DB::table('projects')
                    ->where('id', $project->id)
                    ->update(['github_repo' => is_array($repos) ? ($repos[0] ?? null) : null]);
            });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('github_repos');
        });
    }
};
