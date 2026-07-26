<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The issue template a ticket was filed from, so the detail page can show
     * it. Nullable: blank issues, older tickets, and imports have none.
     */
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('template_id')
                ->nullable()
                ->after('type')
                ->constrained('issue_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
        });
    }
};
