<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_watchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // An explicit unwatch is recorded rather than deleted, so that
            // commenting again does not silently re-subscribe someone who
            // deliberately walked away.
            $table->boolean('watching')->default(true);
            $table->timestamps();

            $table->unique(['issue_id', 'user_id']);
            $table->index(['user_id', 'watching']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_watchers');
    }
};
