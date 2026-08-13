<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\NotifyIssueWebhooksAction;
use App\Actions\WatchIssueAction;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\WebhookEvent;
use App\Models\Issue;
use App\Models\User;
use App\Notifications\IssueNotification;
use App\Support\IssueSearch;
use Illuminate\Support\Facades\Notification;

class IssueObserver
{
    public function created(Issue $issue): void
    {
        $issue->recordActivity('created');

        app(NotifyIssueWebhooksAction::class)->handle($issue, WebhookEvent::Created->value);

        // Filing an issue subscribes you to it. The owner is whoever filed it,
        // which for a service account is nobody worth notifying, and autoWatch
        // handles the null.
        app(WatchIssueAction::class)->autoWatch($issue, $issue->owner);

        IssueSearch::index($issue);
    }

    public function updated(Issue $issue): void
    {
        if ($issue->wasChanged(['title', 'description', 'identifier'])) {
            IssueSearch::index($issue);
        }

        if ($issue->wasChanged('status')) {
            $issue->recordActivity('status_changed', [
                'from' => ($from = $issue->getOriginal('status')) instanceof IssueStatus ? $from->value : null,
                'to' => $issue->status->value,
            ]);

            app(NotifyIssueWebhooksAction::class)->handle($issue, WebhookEvent::StatusChanged->value);
            $this->notifyWatchers($issue, 'issue_status_changed');
        }

        if ($issue->wasChanged('assignee_id')) {
            $issue->recordActivity('assigned', ['to' => $issue->assignee?->name]);
            app(NotifyIssueWebhooksAction::class)->handle($issue, WebhookEvent::Assigned->value);
            $this->notifyAssignee($issue);

            if ($issue->assignee_id !== null) {
                app(WatchIssueAction::class)->autoWatch($issue, User::find($issue->assignee_id));
            }
        }

        if ($issue->wasChanged('priority')) {
            $issue->recordActivity('priority_changed', [
                'from' => ($from = $issue->getOriginal('priority')) instanceof IssuePriority ? $from->value : null,
                'to' => $issue->priority->value,
            ]);
        }

        if ($issue->wasChanged('type')) {
            $issue->recordActivity('type_changed', [
                'from' => ($from = $issue->getOriginal('type')) instanceof IssueType ? $from->value : null,
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
            $archived = $issue->archived_at !== null;

            $archived
                ? $issue->recordActivity('archived', ['reason' => $issue->archive_reason])
                : $issue->recordActivity('unarchived');

            app(NotifyIssueWebhooksAction::class)->handle(
                $issue,
                ($archived ? WebhookEvent::Archived : WebhookEvent::Restored)->value,
            );
        }
    }

    public function deleted(Issue $issue): void
    {
        IssueSearch::forget($issue);
    }

    /**
     * Tell everyone following this issue, except whoever caused the change.
     * The assignee is included by notifiable() whether or not they watch.
     */
    private function notifyWatchers(Issue $issue, string $event): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        Notification::send($issue->notifiable($actor), new IssueNotification($event, $issue, $actor));
    }

    // Notify the new assignee, unless they assigned it to themselves or the change
    // came from an unauthenticated context (e.g. a GitHub webhook). The assignee is
    // loaded by id rather than via the relation, which may be stale after the change.
    private function notifyAssignee(Issue $issue): void
    {
        $actor = auth()->user();

        if ($actor === null || $issue->assignee_id === null || $issue->assignee_id === $actor->id) {
            return;
        }

        User::find($issue->assignee_id)?->notify(new IssueNotification('issue_assigned', $issue, $actor));
    }
}
