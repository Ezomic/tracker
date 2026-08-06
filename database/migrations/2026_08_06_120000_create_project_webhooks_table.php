<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('secret');
            $table->boolean('active')->default(true);
            $table->timestamp('last_delivered_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_webhooks');
    }
};
