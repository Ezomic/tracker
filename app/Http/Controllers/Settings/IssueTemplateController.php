<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueTemplateRequest;
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Organization;
use App\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueTemplateController extends Controller
{
    public function __construct(private readonly CurrentOrganization $current) {}

    public function index(Request $request): Response
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('viewLibrary', $organization);

        return Inertia::render('settings/Templates', [
            'templates' => $this->serializeMany($organization->issueTemplates()->with('labels')->orderBy('name')->get()),
            'labels' => Label::query()->forOrganization($organization)->orderBy('name')->get(['id', 'name', 'color']),
            'projects' => $organization->projects()->orderBy('key')->get(['id', 'key', 'name']),
            'canManage' => $this->currentUser($request)->can('update', $organization),
        ]);
    }

    /**
     * The current organization's templates as JSON, for the new-issue picker.
     */
    public function options(Request $request): JsonResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('viewLibrary', $organization);

        return response()->json(
            $this->serializeMany($organization->issueTemplates()->with('labels')->orderBy('name')->get())
        );
    }

    public function store(StoreIssueTemplateRequest $request): RedirectResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('update', $organization);

        $template = $organization->issueTemplates()->create($request->safe()->except('labels'));
        $template->labels()->sync($request->validated('labels', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template created.')]);

        return back();
    }

    public function update(StoreIssueTemplateRequest $request, IssueTemplate $template): RedirectResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('update', $organization);
        $this->guardBelongsToOrganization($organization, $template);

        $template->update($request->safe()->except('labels'));
        $template->labels()->sync($request->validated('labels', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template updated.')]);

        return back();
    }

    public function destroy(Request $request, IssueTemplate $template): RedirectResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('update', $organization);
        $this->guardBelongsToOrganization($organization, $template);

        $template->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template deleted.')]);

        return back();
    }

    /**
     * @param  Collection<int, IssueTemplate>  $templates
     * @return list<array<string, mixed>>
     */
    private function serializeMany(Collection $templates): array
    {
        return array_values($templates->map(fn (IssueTemplate $template): array => [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'type' => $template->type?->value,
            'priority' => $template->priority?->value,
            'labelIds' => $template->labels->pluck('id')->all(),
            'cadence' => $template->cadence->value,
            'nextRunAt' => $template->next_run_at?->toIso8601String(),
            'targetProjectId' => $template->target_project_id,
        ])->all());
    }

    private function guardBelongsToOrganization(Organization $organization, IssueTemplate $template): void
    {
        abort_unless($template->organization_id === $organization->id, 404);
    }
}
