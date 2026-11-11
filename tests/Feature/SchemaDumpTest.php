<?php

declare(strict_types=1);

use App\Database\PdoSqliteSchemaState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->databasePath = tempnam(sys_get_temp_dir(), 'tracker-schema-');

    config()->set('database.connections.fresh_file', [
        'driver' => 'sqlite',
        'database' => $this->databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
});

afterEach(function () {
    DB::purge('fresh_file');
    @unlink($this->databasePath);
});

it('loads the schema dump through PDO rather than the sqlite3 command-line tool', function () {
    expect(DB::connection('fresh_file')->getSchemaState())->toBeInstanceOf(PdoSqliteSchemaState::class);
});

it('migrates a fresh SQLite file database from the schema dump', function () {
    $dump = database_path('schema/sqlite-schema.sql');

    $exitCode = Artisan::call('migrate', [
        '--database' => 'fresh_file',
        '--schema-path' => $dump,
        '--force' => true,
    ]);

    $schema = Schema::connection('fresh_file');

    expect($exitCode)->toBe(0)
        ->and($schema->hasTable('organizations'))->toBeTrue()
        ->and($schema->hasTable('issues'))->toBeTrue()
        ->and($schema->hasTable('issue_search'))->toBeTrue()
        ->and($schema->hasColumn('users', 'idp_id'))->toBeTrue()
        ->and(DB::connection('fresh_file')->table('migrations')->count())
        ->toBe(substr_count((string) file_get_contents($dump), 'INSERT INTO migrations') + count(glob(database_path('migrations/*.php')) ?: []));
});

it('runs the time tracking removal on top of the schema dump', function () {
    Artisan::call('migrate', [
        '--database' => 'fresh_file',
        '--schema-path' => database_path('schema/sqlite-schema.sql'),
        '--force' => true,
    ]);

    $schema = Schema::connection('fresh_file');

    expect($schema->hasTable('time_entries'))->toBeFalse()
        ->and($schema->hasColumns('issues', ['estimate_minutes']))->toBeFalse()
        ->and($schema->hasColumns('issues', ['invoiceable']))->toBeFalse()
        ->and($schema->hasColumns('issues', ['confirmed_minutes']))->toBeFalse()
        ->and($schema->hasColumns('issues', ['confirmed_at']))->toBeFalse()
        ->and($schema->hasColumns('projects', ['billr_project_id']))->toBeFalse()
        ->and($schema->hasColumns('projects', ['billr_client_id']))->toBeFalse();
});
