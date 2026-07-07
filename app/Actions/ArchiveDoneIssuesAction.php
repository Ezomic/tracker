<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Models\Issue;

class ArchiveDoneIssuesAction
{
    private const ARCHIVE_AFTER_HOURS = 24;

    public function handle(): int
    {
        return Issue::query()
            ->where('status', IssueStatus::Done)
            ->whereNotNull('closed_at')
            ->whereNull('archived_at')
            ->where('closed_at', '<=', now()->subHours(self::ARCHIVE_AFTER_HOURS))
            ->update(['archived_at' => now()]);
    }
}
