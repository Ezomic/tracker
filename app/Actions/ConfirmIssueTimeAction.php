<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\ReportTimeToBillrJob;
use App\Models\Issue;
use App\Models\User;

class ConfirmIssueTimeAction
{
    /**
     * Lock in the confirmed minutes for an issue. If it's invoiceable, queue a
     * job to report that time to Billr so a slow or unreachable Billr never
     * blocks the request; the local confirmation stands regardless, and the
     * job retries with backoff on failure.
     */
    public function handle(Issue $issue, User $user, int $minutes, ?string $billrClientName): void
    {
        $issue->forceFill([
            'confirmed_minutes' => $minutes,
            'confirmed_at' => now(),
        ])->save();

        $issue->recordActivity('time_confirmed', ['minutes' => $minutes], $user->id);

        if ($issue->invoiceable) {
            ReportTimeToBillrJob::dispatch($issue, $minutes, $billrClientName);
        }
    }
}
