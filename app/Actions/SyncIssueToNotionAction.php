<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;
use App\Services\Notion;
use App\Services\NotionException;
use App\Support\NotionIssueProperties;

class SyncIssueToNotionAction
{
    public function __construct(private readonly Notion $notion) {}

    /**
     * Write the issue to its project's Notion database: a new page the first
     * time, the same page after that. An archived issue is trashed in Notion
     * rather than deleted, and comes back if it is restored. An archived issue
     * that never had a page does not get one.
     */
    public function handle(Issue $issue): void
    {
        $project = $issue->project;

        if (! $project->isLinkedToNotion()) {
            return;
        }

        $properties = NotionIssueProperties::for($issue, (string) $project->notion_title_property);
        $archived = $issue->archived_at !== null;

        if ($issue->notion_page_id !== null) {
            try {
                $this->notion->updatePage($issue->notion_page_id, $properties, $archived);

                return;
            } catch (NotionException $e) {
                if ($e->status !== 404 || $archived) {
                    throw $e;
                }

                $this->storePageId($issue, null);
            }
        }

        if ($archived) {
            return;
        }

        $pageId = $this->notion->createPage((string) $project->notion_data_source_id, $properties);

        $this->storePageId($issue, $pageId);
    }

    private function storePageId(Issue $issue, ?string $pageId): void
    {
        Issue::query()->whereKey($issue->id)->toBase()->update(['notion_page_id' => $pageId]);
        $issue->setAttribute('notion_page_id', $pageId);
    }
}
