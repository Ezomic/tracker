<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;
use App\Models\User;

class WatchIssueAction
{
    /**
     * Follow an issue explicitly. Idempotent, and it revives a row that was
     * previously unwatched.
     */
    public function watch(Issue $issue, User $user): void
    {
        $issue->watchers()->syncWithoutDetaching([$user->id => ['watching' => true]]);
    }

    /**
     * Stop following. The row stays, flagged, so auto-watch cannot quietly
     * resubscribe someone who chose to leave.
     */
    public function unwatch(Issue $issue, User $user): void
    {
        $issue->watchers()->syncWithoutDetaching([$user->id => ['watching' => false]]);
    }

    /**
     * Follow as a side effect of doing something that implies interest: filing
     * it, commenting on it, being assigned it. Unlike watch(), this never
     * overrides a deliberate unwatch.
     */
    public function autoWatch(Issue $issue, ?User $user): void
    {
        if ($user === null || $issue->watchers()->where('users.id', $user->id)->exists()) {
            return;
        }

        $issue->watchers()->attach($user->id, ['watching' => true]);
    }
}
