<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ExportIssuesToCsvAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('issues:export {path : Destination path for the exported CSV file}')]
#[Description('Export all issues to a CSV file matching the tracker import schema')]
class ExportIssuesCommand extends Command
{
    public function handle(ExportIssuesToCsvAction $action): int
    {
        $count = $action->handle($this->argument('path'));

        $this->info("Exported {$count} issue(s).");

        return self::SUCCESS;
    }
}
