<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('projects/Index', [
            'projects' => Project::query()
                ->withCount([
                    'issues',
                    'issues as open_count' => fn (Builder $query) => $query
                        ->whereNull('archived_at')
                        ->where('status', '!=', IssueStatus::Done->value),
                ])
                ->orderByDesc('is_favorite')
                ->orderBy('key')
                ->get()
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'key' => $project->key,
                    'name' => $project->name,
                    'description' => $project->description,
                    'color' => $project->color,
                    'isFavorite' => $project->is_favorite,
                    'githubRepos' => $project->github_repos ?? [],
                    'productionUrl' => $project->production_url,
                    'archiveAfterDays' => $project->archive_after_days,
                    'links' => $project->links(),
                    'openCount' => (int) $project->getAttribute('open_count'),
                    'issuesCount' => (int) $project->getAttribute('issues_count'),
                    'keyLocked' => (int) $project->getAttribute('issues_count') > 0,
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

    public function toggleFavorite(Project $project): RedirectResponse
    {
        $project->forceFill(['is_favorite' => ! $project->is_favorite])->save();

        return back();
    }
}
