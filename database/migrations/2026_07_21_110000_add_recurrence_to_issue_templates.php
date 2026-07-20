<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issue_templates', function (Blueprint $table): void {
            $table->string('cadence')->default('none');
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('target_project_id')->nullable()->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issue_templates', function (Blueprint $table): void {
            $table->dropForeign(['target_project_id']);
            $table->dropColumn(['cadence', 'next_run_at', 'target_project_id']);
        });
    }
};
