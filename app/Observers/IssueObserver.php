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

        if ($issue->wasChanged('priority')) {
            $issue->recordActivity('priority_changed', [
                'from' => $issue->getOriginal('priority')?->value,
                'to' => $issue->priority->value,
            ]);
        }

        if ($issue->wasChanged('type')) {
            $issue->recordActivity('type_changed', [
                'from' => $issue->getOriginal('type')?->value,
                'to' => $issue->type->value,
            ]);
        }

        if ($issue->wasChanged('estimate_minutes')) {
            $issue->recordActivity('estimate_changed', [
                'from' => $issue->getOriginal('estimate_minutes'),
                'to' => $issue->estimate_minutes,
            ]);
        }

        if ($issue->wasChanged('parent_id')) {
            $issue->recordActivity('parent_changed', [
                'from' => Issue::query()->whereKey($issue->getOriginal('parent_id'))->value('identifier'),
                'to' => $issue->parent?->identifier,
            ]);
        }

        if ($issue->wasChanged('title')) {
            $issue->recordActivityCollapsing('renamed', [
                'from' => $issue->getOriginal('title'),
                'to' => $issue->title,
            ]);
        }

        if ($issue->wasChanged('description')) {
            $issue->recordActivityCollapsing('description_edited');
        }

        if ($issue->wasChanged('archived_at')) {
            $issue->archived_at !== null
                ? $issue->recordActivity('archived', ['reason' => $issue->archive_reason])
                : $issue->recordActivity('unarchived');
        }
    }
}
