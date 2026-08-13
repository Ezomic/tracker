<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_issue_id')->constrained('issues')->cascadeOnDelete();
            $table->string('relation');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One relation per ordered pair. Writing the same link twice is a
            // no-op rather than a second row.
            $table->unique(['issue_id', 'related_issue_id', 'relation']);
            $table->index(['related_issue_id', 'relation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_links');
    }
};
