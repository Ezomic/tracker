<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('minutes');
            $table->date('spent_on');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['issue_id', 'spent_on']);
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->unsignedInteger('estimate_minutes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn('estimate_minutes');
        });

        Schema::dropIfExists('time_entries');
    }
};
