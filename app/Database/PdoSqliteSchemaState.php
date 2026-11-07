<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\Schema\SqliteSchemaState;

/**
 * Laravel loads a SQLite schema dump into a file database by shelling out to
 * the sqlite3 CLI. Executing it over the existing PDO connection, as Laravel
 * already does for in-memory databases, means installing and migrating need
 * nothing beyond PHP. Writing a dump (`schema:dump`) still uses the CLI.
 */
class PdoSqliteSchemaState extends SqliteSchemaState
{
    /**
     * @param  string  $path
     */
    public function load($path): void
    {
        $this->connection->getPdo()->exec($this->files->get($path));
    }
}
