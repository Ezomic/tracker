<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreProjectRequest;
use App\Http\Requests\Settings\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/Projects', [
            'projects' => Project::withCount('issues')
                ->orderBy('key')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'key' => $project->key,
                    'name' => $project->name,
                    'color' => $project->color,
                    'githubRepos' => $project->github_repos ?? [],
                    'productionUrl' => $project->production_url,
                    'archiveAfterDays' => $project->archive_after_days,
                    'links' => $project->links(),
                    'issuesCount' => $project->issues_count,
                    'keyLocked' => $project->issues_count > 0,
                ]),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Project::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.index');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('projects.index');
    }
}
