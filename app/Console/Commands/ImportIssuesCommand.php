<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ImportIssuesFromCsvAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('issues:import {path : Path to the CSV file to import}')]
#[Description('Import issues from a CSV file matching the tracker export schema')]
class ImportIssuesCommand extends Command
{
    public function handle(ImportIssuesFromCsvAction $action): int
    {
        $result = $action->handle($this->argument('path'));

        $this->info("Imported {$result['imported']} issue(s), skipped {$result['skipped']}.");

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
