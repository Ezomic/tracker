<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\SQLiteConnection as BaseSQLiteConnection;
use Illuminate\Filesystem\Filesystem;

class SQLiteConnection extends BaseSQLiteConnection
{
    public function getSchemaState(?Filesystem $files = null, ?callable $processFactory = null): PdoSqliteSchemaState
    {
        return new PdoSqliteSchemaState($this, $files, $processFactory);
    }
}
