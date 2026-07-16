<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // Board/index/dashboard filter on notArchived + status constantly.
            $table->index(['archived_at', 'status']);
            // The archive job and "recently completed" filter on closed_at.
            $table->index('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex(['archived_at', 'status']);
            $table->dropIndex(['closed_at']);
        });
    }
};
