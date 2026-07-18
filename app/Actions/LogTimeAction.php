<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Duration;
use Illuminate\Support\Carbon;

class LogTimeAction
{
    /**
     * Record time against an issue. The duration is a human string like
     * "1h 30m"; spent_on defaults to today when omitted.
     */
    public function handle(Issue $issue, User $user, string $duration, ?string $spentOn = null, ?string $note = null): TimeEntry
    {
        return $issue->timeEntries()->create([
            'user_id' => $user->id,
            'minutes' => Duration::toMinutes($duration),
            'spent_on' => $spentOn !== null && $spentOn !== '' ? Carbon::parse($spentOn) : Carbon::today(),
            'note' => $note,
        ]);
    }
}
