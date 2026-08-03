<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // One of App\Enums\StatusCategory: what the app reasons about.
            $table->string('category');
            $table->string('color');
            $table->unsignedInteger('position')->default(0);
            // The lane a new issue lands in.
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['project_type_id', 'name']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_type_id')->nullable()->after('category_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('workflow_state_id')->nullable()->after('status')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_state_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_type_id');
        });

        Schema::dropIfExists('workflow_states');
        Schema::dropIfExists('project_types');
    }
};
