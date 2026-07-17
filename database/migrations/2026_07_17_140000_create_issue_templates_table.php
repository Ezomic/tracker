<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Defaults applied to a new issue; null means "don't preset it".
            $table->string('type')->nullable();
            $table->string('priority')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        Schema::create('issue_template_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();

            $table->unique(['issue_template_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_template_label');
        Schema::dropIfExists('issue_templates');
    }
};
