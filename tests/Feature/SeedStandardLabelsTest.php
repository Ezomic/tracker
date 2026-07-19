<?php

declare(strict_types=1);

use App\Models\Label;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

function runSeedLabelsMigration(): void
{
    (require base_path('database/migrations/2026_07_19_230000_seed_standard_labels.php'))->up();
}

it('seeds the standard set for an organization with no labels', function () {
    $organization = Organization::factory()->create();

    runSeedLabelsMigration();

    $names = Label::query()->where('organization_id', $organization->id)->orderBy('name')->pluck('name')->all();

    expect($names)->toContain('backend', 'frontend', 'api', 'security', 'tech-debt')
        ->and($names)->toHaveCount(10);
});

it('leaves an organization that already has labels untouched', function () {
    $organization = Organization::factory()->create();
    Label::factory()->create(['organization_id' => $organization->id, 'name' => 'curated']);

    runSeedLabelsMigration();

    expect(Label::query()->where('organization_id', $organization->id)->pluck('name')->all())
        ->toBe(['curated']);
});

it('is idempotent when run twice', function () {
    $organization = Organization::factory()->create();

    runSeedLabelsMigration();
    runSeedLabelsMigration();

    expect(Label::query()->where('organization_id', $organization->id)->count())->toBe(10);
});

it('no-ops when there are no organizations', function () {
    DB::table('labels')->delete();
    DB::table('organizations')->delete();

    runSeedLabelsMigration();

    expect(Label::query()->count())->toBe(0);
});
