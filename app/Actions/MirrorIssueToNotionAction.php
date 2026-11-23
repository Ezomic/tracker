<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SyncIssueToNotionJob;
use App\Jobs\TrashNotionPageJob;
use App\Models\Issue;
use App\Services\Integrations;

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
            SyncIssueToNotionJob::dispatch($issue)->afterCommit();
        }
    }

    public function forget(Issue $issue): void
    {
        if ($issue->notion_page_id !== null && $this->mirrors($issue)) {
            TrashNotionPageJob::dispatch($issue->notion_page_id)->afterCommit();
        }
    }

    private function mirrors(Issue $issue): bool
    {
        return $this->integrations->notion() && $issue->project->isLinkedToNotion();
    }
}
