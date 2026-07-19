<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class ConfirmIssueTimeAction
{
    /**
     * Lock in the confirmed minutes for an issue. If it's invoiceable, also
     * report that time to Billr — a failed report still leaves the local
     * confirmation in place, but the caller is told it failed so it can
     * surface a clear error instead of looking like nothing happened.
     */
    public function handle(Issue $issue, User $user, int $minutes, ?string $billrClientName, ReportTimeToBillrAction $reportAction): bool
    {
        $issue->forceFill([
            'confirmed_minutes' => $minutes,
            'confirmed_at' => now(),
        ])->save();

        $issue->recordActivity('time_confirmed', ['minutes' => $minutes], $user->id);

        if (! $issue->invoiceable) {
            return true;
        }

        try {
            $reportAction->handle($issue, $minutes, $billrClientName);

            return true;
        } catch (RequestException|ConnectionException) {
            return false;
        }
    }
}
