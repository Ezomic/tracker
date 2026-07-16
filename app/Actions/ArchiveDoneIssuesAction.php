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
                $count += Issue::query()
                    ->where('project_id', $project->id)
                    ->where('status', IssueStatus::Done)
                    ->whereNotNull('closed_at')
                    ->whereNull('archived_at')
                    ->where('closed_at', '<=', now()->subDays($project->archive_after_days))
                    ->update(['archived_at' => now()]);
            });

        return $count;
    }
}
