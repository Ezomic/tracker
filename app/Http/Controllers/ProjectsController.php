<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateProjectAction;
use App\Enums\IssueStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Category;
use App\Models\Project;
use App\Services\CurrentOrganization;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function index(Request $request, CurrentOrganization $current): Response
    {
        $organization = $current->for($this->currentUser($request));

        return Inertia::render('projects/Index', [
            'categories' => Category::orderedTree($organization)
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'parentId' => $category->parent_id,
                    'depth' => Cast::int($category->getAttribute('depth')),
                ])
                ->values()
                ->all(),
            'projects' => $this->currentUser($request)->projects()
                ->inOrganization($organization)
                ->withCount([
                    'issues',
                    'issues as open_count' => fn (Builder $query) => $query
                        ->whereNull('archived_at')
                        ->where('status', '!=', IssueStatus::Done->value),
                ])
                ->withSum('timeEntries', 'minutes')
                ->orderByPivot('is_favorite', 'desc')
                ->orderBy('key')
                ->get()
                ->map(function (Project $project): array {
                    /** @var Pivot $pivot */
                    $pivot = $project->getAttribute('pivot');

                    return [
                        'id' => $project->id,
                        'key' => $project->key,
                        'name' => $project->name,
                        'description' => $project->description,
                        'color' => $project->color,
                        'categoryId' => $project->category_id,
                        'role' => Cast::string($pivot->getAttribute('role')),
                        'isFavorite' => (bool) $pivot->getAttribute('is_favorite'),
                        'githubRepos' => $project->github_repos ?? [],
                        'productionUrl' => $project->production_url,
                        'archiveAfterDays' => $project->archive_after_days,
                        'links' => $project->links(),
                        'openCount' => Cast::int($project->getAttribute('open_count')),
                        'issuesCount' => Cast::int($project->getAttribute('issues_count')),
                        'loggedMinutes' => Cast::int($project->getAttribute('time_entries_sum_minutes') ?? 0),
                        'keyLocked' => Cast::int($project->getAttribute('issues_count')) > 0,
                    ];
                }),
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProjectAction $action, CurrentOrganization $current): RedirectResponse
    {
        $action->handle($request->validated(), $this->currentUser($request), $current->for($this->currentUser($request)));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.index');
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('projects.index');
    }

    public function toggleFavorite(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        $member = $this->currentUser($request)->projects()->find($project->id);
        $pivot = $member?->getAttribute('pivot');
        $current = $pivot instanceof Model && (bool) $pivot->getAttribute('is_favorite');

        $this->currentUser($request)->projects()->updateExistingPivot($project->id, ['is_favorite' => ! $current]);

        return back();
    }
}
