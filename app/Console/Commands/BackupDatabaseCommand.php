<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Cast;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

#[Signature('backup:database')]
#[Description('Copy the sqlite database into storage/app/private/backups, pruning old backups beyond the retention count')]
class BackupDatabaseCommand extends Command
{
    private const RETENTION = 14;

    public function handle(): int
    {
        $databasePath = config('database.connections.sqlite.database');

        if (! is_string($databasePath) || ! File::exists($databasePath)) {
            $this->error('No sqlite database file found at ['.Cast::string($databasePath).'].');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $destination = $disk->path('backups/database-'.now()->format('Y-m-d_His').'.sqlite');
        File::copy($databasePath, $destination);

        $this->info("Backed up database to [{$destination}].");

        $this->pruneOldBackups($disk);

        return self::SUCCESS;
    }

    private function pruneOldBackups(Filesystem $disk): void
    {
        $backups = collect($disk->files('backups'))
            ->filter(fn (string $path) => str_ends_with($path, '.sqlite'))
            ->sortDesc();

        foreach ($backups->slice(self::RETENTION) as $stale) {
            $disk->delete($stale);
        }
    }
}
