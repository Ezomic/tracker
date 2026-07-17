<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function runSeedCalendarMigration(): void
{
    (require database_path('migrations/2026_07_17_180000_seed_calendar_project.php'))->up();
}

it('creates the Calendar project in the founder organization with the founder as owner', function () {
    $founder = User::factory()->create(['email' => 'robbin_thijssen@hotmail.nl']);
    $org = Organization::factory()->create(['name' => 'Thijssen Software']);
    $org->members()->attach($founder->id, ['role' => OrganizationRole::Owner->value]);

    runSeedCalendarMigration();

    $project = Project::query()->where('key', 'CAL')->firstOrFail();
    expect($project->name)->toBe('Calendar')
        ->and($project->organization_id)->toBe($org->id)
        ->and($project->grantFor($founder))->toBe(ProjectLevel::Admin);
});

it('is idempotent and does not duplicate the project', function () {
    $founder = User::factory()->create(['email' => 'robbin_thijssen@hotmail.nl']);
    $org = Organization::factory()->create();
    $org->members()->attach($founder->id, ['role' => OrganizationRole::Owner->value]);

    runSeedCalendarMigration();
    runSeedCalendarMigration();

    expect(Project::query()->where('key', 'CAL')->count())->toBe(1);
});

it('no-ops when the founder does not exist', function () {
    runSeedCalendarMigration();

    expect(Project::query()->where('key', 'CAL')->exists())->toBeFalse();
});
