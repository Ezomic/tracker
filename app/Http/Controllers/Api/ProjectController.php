<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateProjectAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Project::query()->visibleTo($this->currentUser($request))->notArchived()->orderBy('key')->get(['key', 'name', 'color'])
        );
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
