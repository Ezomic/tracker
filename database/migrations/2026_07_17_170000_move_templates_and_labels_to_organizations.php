<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Templates and labels become organization-wide, so they are defined once
     * rather than per project (templates) or per user (labels, the TRACK-92
     * interim). Each existing user owns exactly their personal organization, so
     * a label maps to that org; a template maps to its project's org.
     */
    public function up(): void
    {
        // Owning org per user — everyone owns exactly one after phase 1.
        $ownerOrg = DB::table('organization_user')
            ->where('role', 'owner')
            ->pluck('organization_id', 'user_id');

        Schema::table('labels', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('labels')->get(['id', 'user_id'])->each(function (object $label) use ($ownerOrg): void {
            DB::table('labels')->where('id', $label->id)
                ->update(['organization_id' => $ownerOrg[$label->user_id] ?? null]);
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique(['organization_id', 'name']);
        });

        Schema::table('issue_templates', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('issue_templates')->get(['id', 'project_id'])->each(function (object $template): void {
            $orgId = DB::table('projects')->where('id', $template->project_id)->value('organization_id');
            DB::table('issue_templates')->where('id', $template->id)->update(['organization_id' => $orgId]);
        });

        Schema::table('issue_templates', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'name']);
            $table->dropConstrainedForeignId('project_id');
            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'name']);
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropConstrainedForeignId('organization_id');
            $table->unique(['user_id', 'name']);
        });

        Schema::table('issue_templates', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'name']);
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropConstrainedForeignId('organization_id');
            $table->unique(['project_id', 'name']);
        });
    }
};
