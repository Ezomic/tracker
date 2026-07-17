<?php

declare(strict_types=1);

use App\Models\Project;

function runRestoreDescriptionsMigration(): void
{
    (require database_path('migrations/2026_07_17_110000_restore_wiped_project_descriptions.php'))->up();
}

it('restores a wiped (null) description for a known project', function () {
    $project = Project::factory()->create(['key' => 'FIN', 'description' => null]);

    runRestoreDescriptionsMigration();

    expect($project->fresh()->description)->toContain('household finance tracker');
});

it('does not overwrite a description that still has content', function () {
    $project = Project::factory()->create(['key' => 'FIN', 'description' => 'My custom text']);

    runRestoreDescriptionsMigration();

    expect($project->fresh()->description)->toBe('My custom text');
});

it('leaves unknown project keys untouched', function () {
    $project = Project::factory()->create(['key' => 'XYZ', 'description' => null]);

    runRestoreDescriptionsMigration();

    expect($project->fresh()->description)->toBeNull();
});
