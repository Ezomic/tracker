<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SyncIssueToNotionJob;
use App\Models\Project;

class BackfillIssuesToNotionAction
{
    /**
     * Notion allows about three requests a second, so a backfill spreads its
     * jobs out rather than leaning on the rate-limit retry for all of them.
     */
    private const SPACING_MILLISECONDS = 350;

    /**
     * Queue every live issue of the project that has no Notion page yet, and
     * return how many. Issues already mirrored are left to the normal sync.
     */
    public function handle(Project $project): int
    {
        $queued = 0;

        foreach ($project->issues()->whereNull('notion_page_id')->whereNull('archived_at')->orderBy('id')->lazyById() as $issue) {
            SyncIssueToNotionJob::dispatch($issue)->delay(now()->addMilliseconds($queued * self::SPACING_MILLISECONDS));
            $queued++;
        }

        return $queued;
    }
}
