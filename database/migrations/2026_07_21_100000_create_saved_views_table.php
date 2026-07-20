<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('criteria');
            $table->timestamps();

            $table->index(['user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
