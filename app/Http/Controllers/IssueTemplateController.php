<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreIssueTemplateRequest;
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueTemplateController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('projects/Templates', [
            'project' => [
                'key' => $project->key,
                'name' => $project->name,
            ],
            'templates' => $this->serializeMany($project->issueTemplates()->with('labels')->orderBy('name')->get()),
            // Templates from the user's other projects, offered as a starting
            // point so a Bug template needn't be retyped per project.
            'copyable' => $this->copyable($request, $project),
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'color']),
            'canManage' => $request->user()->can('update', $project),
        ]);
    }

    /**
     * The project's templates as JSON, fetched when the new-issue picker needs
     * them. Bodies are too big to ship as a shared prop on every page load.
     */
    public function options(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json(
            $this->serializeMany($project->issueTemplates()->with('labels')->orderBy('name')->get())
        );
    }

    public function store(StoreIssueTemplateRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $template = $project->issueTemplates()->create($request->safe()->except('labels'));
        $template->labels()->sync($request->validated('labels', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template created.')]);

        return back();
    }

    public function update(StoreIssueTemplateRequest $request, Project $project, IssueTemplate $template): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->guardBelongsToProject($project, $template);

        $template->update($request->safe()->except('labels'));
        $template->labels()->sync($request->validated('labels', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template updated.')]);

        return back();
    }

    public function destroy(Project $project, IssueTemplate $template): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->guardBelongsToProject($project, $template);

        $template->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template deleted.')]);

        return back();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function copyable(Request $request, Project $project): array
    {
        return array_values(IssueTemplate::query()
            ->with(['labels', 'project'])
            ->whereNot('project_id', $project->id)
            ->whereHas('project.members', fn (Builder $members) => $members->whereKey($request->user()->id))
            ->orderBy('name')
            ->get()
            ->map(fn (IssueTemplate $template): array => [
                ...$this->serialize($template),
                'projectKey' => $template->project->key,
            ])
            ->all());
    }

    /**
     * @param  Collection<int, IssueTemplate>  $templates
     * @return list<array<string, mixed>>
     */
    private function serializeMany(Collection $templates): array
    {
        return array_values($templates->map(fn (IssueTemplate $template): array => $this->serialize($template))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(IssueTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'type' => $template->type?->value,
            'priority' => $template->priority?->value,
            'labelIds' => $template->labels->pluck('id')->all(),
        ];
    }

    private function guardBelongsToProject(Project $project, IssueTemplate $template): void
    {
        abort_unless($template->project_id === $project->id, 404);
    }
}
