<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;

class ArchiveIssueAction
{
    /**
     * Archive an issue with an optional reason. Re-archiving an already
     * archived issue just refreshes the reason.
     */
    public function handle(Issue $issue, ?string $reason = null): void
    {
        $issue->forceFill([
            'archived_at' => $issue->archived_at ?? now(),
            'archive_reason' => $reason,
        ])->save();
    }

    public function unarchive(Issue $issue): void
    {
        $issue->forceFill([
            'archived_at' => null,
            'archive_reason' => null,
        ])->save();
    }
}
