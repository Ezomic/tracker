<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Project;
use App\Services\Notion;
use App\Services\NotionException;

class LinkNotionDatabaseAction
{
    public function __construct(private readonly Notion $notion) {}

    /**
     * Point a project at a Notion database, after Notion has confirmed the
     * integration can see it and its columns fit. Nothing is stored unless
     * every step succeeds. Page ids belong to one database, so they are dropped
     * when the project moves to a different one; linking the same database
     * again keeps them, and its rows keep being updated rather than duplicated.
     *
     * @throws NotionException
     */
    public function handle(Project $project, string $databaseId): void
    {
        $dataSourceId = $this->notion->dataSourceFor($databaseId);
        $titleProperty = $this->notion->prepareSchema($dataSourceId);

        if ($project->notion_database_id !== $databaseId) {
            $project->issues()->toBase()->update(['notion_page_id' => null]);
        }

        $project->forceFill([
            'notion_database_id' => $databaseId,
            'notion_data_source_id' => $dataSourceId,
            'notion_title_property' => $titleProperty,
        ])->save();
    }
}
