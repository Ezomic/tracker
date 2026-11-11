<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activities')
            ->whereIn('type', ['time_logged', 'time_confirmed', 'estimate_changed'])
            ->delete();

        Schema::dropIfExists('time_entries');

        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn(['estimate_minutes', 'invoiceable', 'confirmed_minutes', 'confirmed_at']);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['billr_project_id', 'billr_client_id']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedBigInteger('billr_project_id')->nullable();
            $table->unsignedBigInteger('billr_client_id')->nullable();
        });

        Schema::table('issues', function (Blueprint $table): void {
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->boolean('invoiceable')->default(false);
            $table->unsignedInteger('confirmed_minutes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
        });

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
    }
};
