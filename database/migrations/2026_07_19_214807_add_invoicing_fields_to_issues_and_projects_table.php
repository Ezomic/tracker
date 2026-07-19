<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->boolean('invoiceable')->default(false)->after('estimate_minutes');
            $table->unsignedInteger('confirmed_minutes')->nullable()->after('invoiceable');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_minutes');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('billr_project_id')->nullable()->after('production_url');
            $table->unsignedInteger('billr_client_id')->nullable()->after('billr_project_id');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['invoiceable', 'confirmed_minutes', 'confirmed_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['billr_project_id', 'billr_client_id']);
        });
    }
};
