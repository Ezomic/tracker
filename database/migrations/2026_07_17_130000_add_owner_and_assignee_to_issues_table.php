<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // Owner is the reporter: stamped on creation, never reassigned.
            $table->foreignId('owner_id')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropConstrainedForeignId('assignee_id');
        });
    }
};
