<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;

class ArchiveDoneIssuesAction
{
    public function handle(): int
    {
        $count = 0;

        // Projects with a null archive_after_days never auto-archive.
        Project::query()
            ->whereNotNull('archive_after_days')
            ->get(['id', 'archive_after_days'])
            ->each(function (Project $project) use (&$count): void {
                $days = $project->archive_after_days;

                $count += Issue::query()
                    ->where('project_id', $project->id)
                    ->where('status', IssueStatus::Done)
                    ->whereNotNull('closed_at')
                    ->whereNull('archived_at')
                    ->where('closed_at', '<=', now()->subDays($days))
                    ->update([
                        'archived_at' => now(),
                        'archive_reason' => "Auto-archived {$days} ".($days === 1 ? 'day' : 'days').' after being done',
                    ]);
            });

        return $count;
    }
}
