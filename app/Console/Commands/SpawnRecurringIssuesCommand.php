<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SpawnRecurringIssuesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('issues:spawn-recurring')]
#[Description('File issues from templates whose recurrence is due')]
class SpawnRecurringIssuesCommand extends Command
{
    public function handle(SpawnRecurringIssuesAction $action): int
    {
        $count = $action->handle();

        $this->info("Spawned {$count} recurring issue(s).");

        return self::SUCCESS;
    }
}
