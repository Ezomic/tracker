<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('repository');
            $table->string('sha', 64);
            $table->string('branch');
            $table->text('message');
            $table->string('author_name')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('committed_at');
            $table->timestamps();

            $table->unique(['repository', 'sha']);
            $table->index(['issue_id', 'committed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commits');
    }
};
