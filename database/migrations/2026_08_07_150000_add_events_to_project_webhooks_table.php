<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_webhooks', function (Blueprint $table): void {
            // Null means status changes only, which is exactly what every
            // existing endpoint was subscribed to before this column existed.
            $table->json('events')->nullable()->after('secret');
        });
    }

    public function down(): void
    {
        Schema::table('project_webhooks', function (Blueprint $table): void {
            $table->dropColumn('events');
        });
    }
};
