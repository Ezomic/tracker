<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\SaveProjectTypeAction;
use App\Enums\StatusCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveProjectTypeRequest;
use App\Models\ProjectType;
use App\Models\WorkflowState;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectTypeController extends Controller
{
    public function __construct(private readonly CurrentOrganization $current) {}

    public function index(Request $request): Response
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('viewLibrary', $organization);

        return Inertia::render('settings/ProjectTypes', [
            'types' => $organization->projectTypes()
                ->with('states')
                ->withCount('projects')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (ProjectType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'description' => $type->description,
                    'isDefault' => $type->is_default,
                    'projectsCount' => $type->getAttribute('projects_count'),
                    'states' => $type->states->map(fn (WorkflowState $state): array => [
                        'id' => $state->id,
                        'name' => $state->name,
                        'category' => $state->category->value,
                        'color' => $state->color,
                        'isDefault' => $state->is_default,
                    ])->all(),
                ])
                ->all(),
            'categories' => array_map(
                fn (StatusCategory $category): string => $category->value,
                StatusCategory::cases(),
            ),
            'canManage' => $this->currentUser($request)->can('update', $organization),
        ]);
    }

    public function store(SaveProjectTypeRequest $request, SaveProjectTypeAction $action): RedirectResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('update', $organization);

        $action->handle($organization, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project type created.')]);

        return to_route('project-types.index');
    }

    public function update(SaveProjectTypeRequest $request, ProjectType $projectType, SaveProjectTypeAction $action): RedirectResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('update', $organization);
        abort_unless($projectType->organization_id === $organization->id, 404);

        $action->handle($organization, $request->validated(), $projectType);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project type updated.')]);

        return to_route('project-types.index');
    }

    public function destroy(Request $request, ProjectType $projectType): RedirectResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('update', $organization);
        abort_unless($projectType->organization_id === $organization->id, 404);

        if ($projectType->is_default || $projectType->projects()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This project type is in use and cannot be deleted.')]);

            return to_route('project-types.index');
        }

        $projectType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project type deleted.')]);

        return to_route('project-types.index');
    }
}
