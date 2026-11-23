<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SyncIssueToNotionJob;
use App\Jobs\TrashNotionPageJob;
use App\Models\Issue;
use App\Services\Integrations;
use Closure;
use Throwable;

class MirrorIssueToNotionAction
{
    public function __construct(private readonly Integrations $integrations) {}

    /**
     * Queue a sync of one issue when it belongs in Notion. Called from every
     * place an issue changes, so it is cheap when the answer is no.
     */
    public function handle(Issue $issue): void
    {
        if ($this->mirrors($issue)) {
            $this->guarded(fn () => SyncIssueToNotionJob::dispatch($issue)->afterCommit());
        }
    }

    public function forget(Issue $issue): void
    {
        if ($issue->notion_page_id !== null && $this->mirrors($issue)) {
            $this->guarded(fn () => TrashNotionPageJob::dispatch($issue->notion_page_id)->afterCommit());
        }
    }

    /**
     * On the sync queue driver the job runs inside the dispatch, and a Notion
     * error would otherwise surface in the request that saved the issue.
     */
    private function guarded(Closure $dispatch): void
    {
        try {
            $dispatch();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function mirrors(Issue $issue): bool
    {
        return $this->integrations->notion() && $issue->project->isLinkedToNotion();
    }
}
