<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueTemplateRequest;
use App\Models\IssueTemplate;
use App\Models\Organization;
use App\Models\Project;
use App\Services\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project' => ['sometimes', 'string', 'exists:projects,key'],
        ]);

        $organization = isset($validated['project'])
            ? Project::query()->where('key', $validated['project'])->firstOrFail()->organization
            : Organization::query()->visibleTo($this->currentUser($request))->orderBy('name')->first();

        abort_if($organization === null, 404);

        $this->authorize('viewLibrary', $organization);

        $templates = $organization->issueTemplates()->with('labels')->orderBy('name')->get();

        return response()->json($templates->map(fn (IssueTemplate $template): array => $this->payload($template))->all());
    }

    public function store(StoreIssueTemplateRequest $request, CurrentOrganization $current): JsonResponse
    {
        $organization = $current->require($this->currentUser($request));
        $this->authorize('update', $organization);

        $template = $organization->issueTemplates()->create($request->safe()->except('labels'));
        $template->labels()->sync($this->intList($request->validated('labels', [])));

        return response()->json($this->payload($template->load('labels')), 201);
    }

    public function update(StoreIssueTemplateRequest $request, IssueTemplate $template, CurrentOrganization $current): JsonResponse
    {
        $organization = $current->require($this->currentUser($request));
        $this->authorize('update', $organization);
        abort_unless($template->organization_id === $organization->id, 404);

        $template->update($request->safe()->except('labels'));
        $template->labels()->sync($this->intList($request->validated('labels', [])));

        return response()->json($this->payload($template->refresh()->load('labels')));
    }

    public function destroy(Request $request, IssueTemplate $template, CurrentOrganization $current): JsonResponse
    {
        $organization = $current->require($this->currentUser($request));
        $this->authorize('update', $organization);
        abort_unless($template->organization_id === $organization->id, 404);

        $template->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(IssueTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'type' => $template->type?->value,
            'priority' => $template->priority?->value,
            'labels' => $template->labels->pluck('name')->all(),
            'cadence' => $template->cadence->value,
            'nextRunAt' => $template->next_run_at?->toIso8601String(),
            'targetProjectId' => $template->target_project_id,
        ];
    }
}
