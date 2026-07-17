<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            // Labels were global and unscoped, which leaked across tenants once
            // registration opened. Give them an owner. This becomes
            // organization_id when the organization entity lands.
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // A name only has to be unique within its owner's set — one tenant
            // taking "bug" must not block another.
            $table->dropUnique(['name']);
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique('name');
        });
    }
};
