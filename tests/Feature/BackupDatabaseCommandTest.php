<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->workDir = sys_get_temp_dir().'/tracker-backup-'.uniqid();
    File::makeDirectory($this->workDir);
});

afterEach(function () {
    Carbon::setTestNow();
    File::deleteDirectory($this->workDir);
});

function walDatabase(string $path, int $rows = 3): PDO
{
    $pdo = new PDO('sqlite:'.$path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA wal_autocheckpoint = 0');
    $pdo->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT NOT NULL)');

    $pdo->beginTransaction();
    $insert = $pdo->prepare('INSERT INTO notes (body) VALUES (?)');
    for ($i = 1; $i <= $rows; $i++) {
        $insert->execute(["note {$i}"]);
    }
    $pdo->commit();

    return $pdo;
}

function openBackup(string $path): PDO
{
    $pdo = new PDO('sqlite:'.$path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

function onlyBackup(): string
{
    $files = Storage::disk('local')->files('backups');
    expect($files)->toHaveCount(1);

    return Storage::disk('local')->path($files[0]);
}

it('writes a consistent, openable backup of a WAL database that has an open write', function () {
    $source = $this->workDir.'/live.sqlite';
    $live = walDatabase($source);
    config(['database.connections.sqlite.database' => $source]);

    expect(filesize($source.'-wal'))->toBeGreaterThan(0);

    $live->beginTransaction();
    $live->exec("INSERT INTO notes (body) VALUES ('uncommitted')");

    $this->artisan('backup:database')->assertSuccessful();

    $backup = openBackup(onlyBackup());
    expect($backup->query('PRAGMA integrity_check')->fetchColumn())->toBe('ok')
        ->and($backup->query('SELECT body FROM notes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN))
        ->toBe(['note 1', 'note 2', 'note 3']);

    $live->rollBack();
});

it('backs up a larger database in full', function () {
    $source = $this->workDir.'/live.sqlite';
    $live = walDatabase($source, 20000);
    config(['database.connections.sqlite.database' => $source]);

    $this->artisan('backup:database')->assertSuccessful();

    expect(openBackup(onlyBackup())->query('SELECT COUNT(*) FROM notes')->fetchColumn())->toBe(20000)
        ->and($live->query('SELECT COUNT(*) FROM notes')->fetchColumn())->toBe(20000);
});

it('creates the backups directory when it is missing', function () {
    $source = $this->workDir.'/live.sqlite';
    walDatabase($source);
    config(['database.connections.sqlite.database' => $source]);

    expect(Storage::disk('local')->exists('backups'))->toBeFalse();

    $this->artisan('backup:database')->assertSuccessful();

    expect(Storage::disk('local')->files('backups'))->toHaveCount(1);
});

it('writes to a storage path containing spaces and quotes', function () {
    $source = $this->workDir.'/live.sqlite';
    walDatabase($source);
    config(['database.connections.sqlite.database' => $source]);

    $root = $this->workDir."/it's a \"odd\" dir";
    File::makeDirectory($root);
    config(['filesystems.disks.local.root' => $root]);
    Storage::forgetDisk('local');

    $this->artisan('backup:database')->assertSuccessful();

    $backups = File::files($root.'/backups');
    expect($backups)->toHaveCount(1)
        ->and(openBackup($backups[0]->getPathname())->query('SELECT COUNT(*) FROM notes')->fetchColumn())->toBe(3);
});

it('reads a source database whose path contains spaces and quotes', function () {
    $dir = $this->workDir."/src it's";
    File::makeDirectory($dir);
    $source = $dir.'/live.sqlite';
    walDatabase($source);
    config(['database.connections.sqlite.database' => $source]);

    $this->artisan('backup:database')->assertSuccessful();

    expect(openBackup(onlyBackup())->query('SELECT COUNT(*) FROM notes')->fetchColumn())->toBe(3);
});

it('fails without touching the existing file when the target already exists', function () {
    $source = $this->workDir.'/live.sqlite';
    walDatabase($source);
    config(['database.connections.sqlite.database' => $source]);

    Carbon::setTestNow('2026-10-15 12:00:00');
    $disk = Storage::disk('local');
    $disk->put('backups/database-2026-10-15_120000.sqlite', 'earlier backup');

    $this->artisan('backup:database')->assertFailed();

    expect($disk->get('backups/database-2026-10-15_120000.sqlite'))->toBe('earlier backup')
        ->and($disk->files('backups'))->toHaveCount(1);
});

it('fails and leaves no partial backup when the source is not a sqlite database', function () {
    $source = $this->workDir.'/garbage.sqlite';
    File::put($source, str_repeat('not a database', 100));
    config(['database.connections.sqlite.database' => $source]);

    $this->artisan('backup:database')->assertFailed();

    expect(Storage::disk('local')->files('backups'))->toBeEmpty();
});

it('fails gracefully when no database file exists', function () {
    config(['database.connections.sqlite.database' => ':memory:']);

    $this->artisan('backup:database')->assertFailed();

    expect(Storage::disk('local')->files('backups'))->toBeEmpty();
});

it('prunes backups beyond the retention count', function () {
    $source = $this->workDir.'/live.sqlite';
    walDatabase($source);
    config(['database.connections.sqlite.database' => $source]);

    $disk = Storage::disk('local');
    $disk->makeDirectory('backups');
    for ($i = 1; $i <= 14; $i++) {
        $disk->put("backups/database-2026-01-{$i}_000000.sqlite", 'old backup');
    }

    $this->artisan('backup:database')->assertSuccessful();

    expect($disk->files('backups'))->toHaveCount(14);
});
