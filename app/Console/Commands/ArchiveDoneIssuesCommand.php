<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ArchiveDoneIssuesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('issues:archive-done')]
#[Description('Archive issues that have been done for at least 24 hours')]
class ArchiveDoneIssuesCommand extends Command
{
    public function handle(ArchiveDoneIssuesAction $action): int
    {
        $count = $action->handle();

        $this->info("Archived {$count} issue(s).");

        return self::SUCCESS;
    }
}
