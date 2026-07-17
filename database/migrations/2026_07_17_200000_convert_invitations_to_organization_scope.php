<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member')->after('email');
        });

        // Existing invitations were project-scoped; adopt the project's organization
        // and treat the invitee as a full member (they were joining to collaborate).
        DB::table('invitations')
            ->whereNotNull('project_id')
            ->update([
                'organization_id' => DB::raw('(select organization_id from projects where projects.id = invitations.project_id)'),
            ]);

        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'email']);
            $table->foreignId('project_id')->nullable()->change();
            $table->string('level')->nullable()->change();
            $table->unique(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'email']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('role');
            $table->unique(['project_id', 'email']);
        });
    }
};
