<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ArchiveDoneIssuesAction
{
    public function handle(): int
    {
        $count = 0;

        // Projects with a null archive_after_days never auto-archive.
        Project::query()
            ->whereNotNull('archive_after_days')
            ->get()
            ->each(function (Project $project) use (&$count): void {
                $count += $this->sweep($project);
            });

        return $count;
    }

    /**
     * Archive through the model rather than a mass update, so the observer
     * records the timeline entry and fires issue.archived for each issue.
     *
     * The webhook cap is reset per project and flushed after it: endpoints
     * belong to one project, so each gets its own budget and a summary that
     * counts only its own suppressed deliveries.
     */
    private function sweep(Project $project): int
    {
        $days = $project->archive_after_days;

        if ($days === null) {
            return 0;
        }

        $reason = "Auto-archived {$days} ".($days === 1 ? 'day' : 'days').' after being done';
        $count = 0;
        NotifyIssueWebhooksAction::reset();

        Issue::query()
            ->where('project_id', $project->id)
            ->where('status', IssueStatus::Done)
            ->whereNotNull('closed_at')
            ->whereNull('archived_at')
            ->where('closed_at', '<=', now()->subDays($days))
            ->chunkById(100, function (Collection $issues) use ($project, $reason, &$count): void {
                foreach ($issues as $issue) {
                    // The webhook payload reads the project key; it is already in hand.
                    $issue->setRelation('project', $project);
                    $issue->forceFill(['archived_at' => now(), 'archive_reason' => $reason])->save();
                    $count++;
                }
            });

        app(NotifyIssueWebhooksAction::class)->flushSuppressed($project);

        return $count;
    }
}
