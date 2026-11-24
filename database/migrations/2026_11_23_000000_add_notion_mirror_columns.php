<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('notion_database_id', 32)->nullable();
            $table->string('notion_data_source_id', 64)->nullable();
            $table->string('notion_title_property')->nullable();
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->string('notion_page_id', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn('notion_page_id');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['notion_database_id', 'notion_data_source_id', 'notion_title_property']);
        });
    }
};
