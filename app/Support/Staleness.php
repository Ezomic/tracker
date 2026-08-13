<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * An issue is stale when nobody has touched it for a while.
 *
 * Deliberately measured from the last recorded activity rather than from
 * created_at: an issue filed a year ago and commented on yesterday is not
 * forgotten, and one filed last week that nobody has looked at since might be.
 * The activities table already records every status change, comment,
 * assignment and label edit, so it is the right clock.
 */
final class Staleness
{
    public static function defaultDays(): int
    {
        return Config::integer('tracker.stale_after_days', 30);
    }

    public static function daysFor(?Project $project): int
    {
        if (! $project instanceof Project) {
            return self::defaultDays();
        }

        return $project->stale_after_days ?? self::defaultDays();
    }

    /**
     * Open issues whose most recent activity predates their project's
     * threshold. Done and archived issues are excluded: auto-archive already
     * handles finished work, and this is about work that stopped moving.
     *
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    public static function scope(Builder $query): Builder
    {
        $default = self::defaultDays();

        return $query
            ->notArchived()
            ->where('status', '!=', IssueStatus::Done->value)
            ->whereRaw(
                'coalesce((select max(created_at) from activities where activities.issue_id = issues.id), issues.created_at) < datetime(?, "-" || coalesce((select stale_after_days from projects where projects.id = issues.project_id), ?) || " days")',
                [Carbon::now()->toDateTimeString(), $default],
            );
    }

    /**
     * When an issue last showed a sign of life, for reporting how long it has
     * been quiet.
     */
    public static function lastActivityAt(Issue $issue): Carbon
    {
        $last = $issue->activities()->max('created_at');

        return $last === null
            ? Carbon::parse($issue->created_at)
            : Carbon::parse(Cast::string($last));
    }
}
