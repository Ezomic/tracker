<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BackfillIssuesToNotionAction;
use App\Actions\LinkNotionDatabaseAction;
use App\Http\Requests\LinkNotionDatabaseRequest;
use App\Models\Project;
use App\Services\NotionException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectNotionController extends Controller
{
    public function index(Project $project): Response
    {
        $this->authorize('manageNotion', $project);

        return Inertia::render('projects/Notion', [
            'project' => [
                'key' => $project->key,
                'name' => $project->name,
            ],
            'notion' => [
                'linked' => $project->isLinkedToNotion(),
                'databaseId' => $project->notion_database_id,
                'mirrored' => $project->issues()->whereNotNull('notion_page_id')->count(),
                'pending' => $project->issues()->whereNull('notion_page_id')->whereNull('archived_at')->count(),
            ],
        ]);
    }

    public function update(LinkNotionDatabaseRequest $request, Project $project, LinkNotionDatabaseAction $action): RedirectResponse
    {
        try {
            $action->handle($project, $request->databaseId());
        } catch (NotionException $e) {
            return back()->withErrors(['database' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notion database linked.')]);

        return to_route('projects.notion.index', $project->key);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('manageNotion', $project);

        $project->forceFill([
            'notion_database_id' => null,
            'notion_data_source_id' => null,
            'notion_title_property' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notion database unlinked.')]);

        return to_route('projects.notion.index', $project->key);
    }

    public function sync(Project $project, BackfillIssuesToNotionAction $action): RedirectResponse
    {
        $this->authorize('manageNotion', $project);

        if (! $project->isLinkedToNotion()) {
            return back()->withErrors(['database' => __('Link a Notion database first.')]);
        }

        $queued = $action->handle($project);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count issue queued for Notion.|:count issues queued for Notion.', $queued)]);

        return to_route('projects.notion.index', $project->key);
    }
}
