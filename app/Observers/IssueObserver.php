<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Issue;

class IssueObserver
{
    public function created(Issue $issue): void
    {
        $issue->recordActivity('created');
    }

    public function updated(Issue $issue): void
    {
        if ($issue->wasChanged('status')) {
            $issue->recordActivity('status_changed', [
                'from' => $issue->getOriginal('status')?->value,
                'to' => $issue->status->value,
            ]);
        }

        if ($issue->wasChanged('assignee_id')) {
            $issue->recordActivity('assigned', ['to' => $issue->assignee?->name]);
        }

        if ($issue->wasChanged('archived_at')) {
            $issue->archived_at !== null
                ? $issue->recordActivity('archived', ['reason' => $issue->archive_reason])
                : $issue->recordActivity('unarchived');
        }
    }
}
