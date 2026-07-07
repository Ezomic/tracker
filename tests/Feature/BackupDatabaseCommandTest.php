<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('copies the sqlite database into storage/app/private/backups', function () {
    $sourcePath = sys_get_temp_dir().'/tracker-backup-test-'.uniqid().'.sqlite';
    File::put($sourcePath, 'fake sqlite contents');
    config(['database.connections.sqlite.database' => $sourcePath]);

    $this->artisan('backup:database')->assertSuccessful();

    $backups = Storage::disk('local')->files('backups');
    expect($backups)->toHaveCount(1)
        ->and(Storage::disk('local')->get($backups[0]))->toBe('fake sqlite contents');

    File::delete($sourcePath);
});

it('fails gracefully when no database file exists', function () {
    config(['database.connections.sqlite.database' => ':memory:']);

    $this->artisan('backup:database')->assertFailed();

    expect(Storage::disk('local')->files('backups'))->toBeEmpty();
});

it('prunes backups beyond the retention count', function () {
    $sourcePath = sys_get_temp_dir().'/tracker-backup-test-'.uniqid().'.sqlite';
    File::put($sourcePath, 'fake sqlite contents');
    config(['database.connections.sqlite.database' => $sourcePath]);

    $disk = Storage::disk('local');
    $disk->makeDirectory('backups');
    for ($i = 1; $i <= 14; $i++) {
        $disk->put("backups/database-2026-01-{$i}_000000.sqlite", 'old backup');
    }

    $this->artisan('backup:database')->assertSuccessful();

    expect($disk->files('backups'))->toHaveCount(14);

    File::delete($sourcePath);
});
