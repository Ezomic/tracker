<?php

declare(strict_types=1);

use App\Models\Project;
use Illuminate\Support\Facades\DB;

function runArchiveDefaultMigration(): void
{
    $migration = require database_path('migrations/2026_10_09_180000_default_archive_after_days_to_a_week.php');
    $migration->up();
}

it('gives new projects a week before done issues auto-archive', function () {
    DB::table('projects')->insert(['key' => 'NEW', 'name' => 'New', 'color' => '#112233', 'created_at' => now(), 'updated_at' => now()]);

    expect(Project::query()->where('key', 'NEW')->firstOrFail()->archive_after_days)->toBe(7);
});

it('moves projects on the old one-day default to a week and leaves other choices alone', function () {
    $oneDay = Project::factory()->create(['key' => 'ONE', 'archive_after_days' => 1]);
    $week = Project::factory()->create(['key' => 'WEK', 'archive_after_days' => 7]);
    $fortnight = Project::factory()->create(['key' => 'FOR', 'archive_after_days' => 14]);
    $custom = Project::factory()->create(['key' => 'CUS', 'archive_after_days' => 21]);
    $never = Project::factory()->create(['key' => 'NEV', 'archive_after_days' => null]);

    runArchiveDefaultMigration();

    expect($oneDay->refresh()->archive_after_days)->toBe(7)
        ->and($week->refresh()->archive_after_days)->toBe(7)
        ->and($fortnight->refresh()->archive_after_days)->toBe(14)
        ->and($custom->refresh()->archive_after_days)->toBe(21)
        ->and($never->refresh()->archive_after_days)->toBeNull();
});

it('does nothing when no project is on the one-day default', function () {
    $project = Project::factory()->create(['key' => 'WEK', 'archive_after_days' => 30]);

    runArchiveDefaultMigration();

    expect($project->refresh()->archive_after_days)->toBe(30);
});
