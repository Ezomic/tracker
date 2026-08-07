<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateProjectAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterProjectsApiRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function index(FilterProjectsApiRequest $request): JsonResponse
    {
        $projects = Project::query()
            ->visibleTo($this->currentUser($request))
            ->when($request->archived() === 'exclude', fn (Builder $query) => $query->notArchived())
            ->when($request->archived() === 'only', fn (Builder $query) => $query->whereNotNull('archived_at'))
            ->orderBy('key')
            ->get(['key', 'name', 'color', 'category_id', 'archived_at']);

        return response()->json($projects->map(fn (Project $project): array => [
            'key' => $project->key,
            'name' => $project->name,
            'color' => $project->color,
            // The only signal distinguishing a Workflow project from a Games or
            // Mods one, for a consumer that cannot infer it from a repo.
            'category_id' => $project->category_id,
            // Absence used to be indistinguishable from never having existed.
            'archived_at' => $project->archived_at?->toIso8601String(),
        ])->all());
    }

    public function store(StoreProjectRequest $request, CreateProjectAction $action, CurrentOrganization $current): JsonResponse
    {
        $this->authorize('create', Project::class);

        $user = $this->currentUser($request);
        $project = $action->handle($request->validated(), $user, $current->for($user));

        return response()->json($this->payload($project), 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return response()->json($this->payload($project->refresh()));
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        if (! $project->isArchived()) {
            $project->forceFill(['archived_at' => now()])->save();
        }

        return response()->json($this->payload($project));
    }

    public function restore(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->forceFill(['archived_at' => null])->save();

        return response()->json($this->payload($project));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Project $project): array
    {
        return [
            'key' => $project->key,
            'name' => $project->name,
            'description' => $project->description,
            'color' => $project->color,
            'categoryId' => $project->category_id,
            'githubRepos' => $project->github_repos ?? [],
            'productionUrl' => $project->production_url,
            'archiveAfterDays' => $project->archive_after_days,
            'archivedAt' => $project->archived_at?->toIso8601String(),
            'url' => url('/'.$project->key.'/tickets'),
        ];
    }
}
