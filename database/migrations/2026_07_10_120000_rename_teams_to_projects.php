<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('teams', 'projects');

        Schema::table('projects', function (Blueprint $table) {
            $table->string('color')->default('#6e6d65')->after('name');
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->renameColumn('team_id', 'project_id');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->renameColumn('project_id', 'team_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('color');
        });

        Schema::rename('projects', 'teams');
    }
};
