<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ReassignIssuesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('issues:reassign {path : Path to a JSON object mapping oldIdentifier => targetProjectKey}')]
#[Description('Re-key issues into their target projects, renumbering per project. Target projects must already exist.')]
class ReassignIssuesCommand extends Command
{
    public function handle(ReassignIssuesAction $action): int
    {
        $path = $this->argument('path');

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read map file at [{$path}].");
        }

        /** @var array<string, string> $map */
        $map = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        $result = $action->handle($map);

        $this->info("Moved {$result['moved']} issue(s), skipped {$result['skipped']} already in place.");

        foreach ($result['missing'] as $identifier) {
            $this->warn("Not found, skipped: {$identifier}");
        }

        return self::SUCCESS;
    }
}
