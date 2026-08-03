<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ChangeProjectTypeAction;
use App\Actions\CreateProjectAction;
use App\Enums\IssueStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectType;
use App\Services\CurrentOrganization;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Builder;
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
            'projectTypes' => ProjectType::query()
                ->where('organization_id', $organization?->id)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (ProjectType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                ])
                ->all(),
            'projects' => $this->currentUser($request)->projects()
                ->notArchived()
                ->inOrganization($organization)
                ->withCount([
                    'issues',
                    'issues as open_count' => fn (Builder $query) => $query
                        ->whereNull('archived_at')
                        ->where('status', '!=', IssueStatus::Done->value),
                ])
                ->withSum('timeEntries', 'minutes')
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
                        'projectTypeId' => $project->project_type_id,
                        'role' => Cast::string($pivot->getAttribute('role')),
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

    public function update(UpdateProjectRequest $request, Project $project, ChangeProjectTypeAction $action): RedirectResponse
    {
        $this->authorize('update', $project);

        $attributes = $request->validated();
        $selectsType = array_key_exists('project_type_id', $attributes);
        $typeId = ($attributes['project_type_id'] ?? null) === null ? null : Cast::int($attributes['project_type_id']);
        unset($attributes['project_type_id']);

        $project->update($attributes);

        if ($selectsType && $typeId !== $project->project_type_id) {
            $action->handle($project, $typeId === null ? null : ProjectType::find($typeId));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('projects.index');
    }
}
