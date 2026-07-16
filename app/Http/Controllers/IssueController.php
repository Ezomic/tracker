<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Http\Requests\FilterIssuesRequest;
use App\Http\Requests\StoreIssueWebRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\UpdateIssueStatusRequest;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function index(FilterIssuesRequest $request, ?Project $project = null): Response
    {
        $filters = $request->validated();

        if ($project !== null) {
            $filters['project_id'] = $project->id;
        }

        return Inertia::render('issues/Index', [
            'issues' => Issue::query()
                ->notArchived()
                ->withCount('children')
                ->with(['project', 'labels'])
                ->when($project, fn (Builder $query) => $query->where('project_id', $project->id))
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', '%'.$search.'%'))
                ->when($filters['project_id'] ?? null, fn (Builder $query, int $projectId) => $query->where('project_id', $projectId))
                ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
                ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
                ->when($filters['label_id'] ?? null, fn (Builder $query, int $labelId) => $query->whereHas('labels', fn (Builder $q) => $q->where('labels.id', $labelId)))
                ->latest()
                ->get()
                ->map($this->serialize(...)),
            'projects' => Project::query()
                ->orderBy('key')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'key' => $project->key,
                    'name' => $project->name,
                    'color' => $project->color,
                    'links' => $project->links(),
                ]),
            'epics' => $this->eligibleParents(),
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => $filters,
        ]);
    }

    public function store(StoreIssueWebRequest $request, CreateIssueAction $action): RedirectResponse
    {
        $project = Project::where('id', $request->validated('project_id'))->firstOrFail();
        $parent = $this->findParent($request->validated('parent_id'));

        $issue = $action->handle(
            project: $project,
            title: $request->validated('title'),
            type: IssueType::from($request->validated('type')),
            description: $request->validated('description'),
            parent: $parent,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue created.')]);

        return to_route('issues.show', $issue);
    }

    public function show(Issue $issue): Response
    {
        $issue->load(['project', 'parent', 'labels', 'children' => fn ($query) => $query->orderBy('number')]);

        return Inertia::render('issues/Show', [
            'issue' => $this->serialize($issue),
            'epics' => $this->eligibleParents($issue),
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function update(UpdateIssueRequest $request, Issue $issue): RedirectResponse
    {
        $issue->update($request->safe()->except('labels'));
        $issue->labels()->sync($request->validated('labels', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue updated.')]);

        return to_route('issues.show', $issue);
    }

    public function board(?Project $project = null): Response
    {
        return Inertia::render('issues/Board', [
            'issues' => Issue::query()
                ->notArchived()
                ->withCount('children')
                ->with(['project', 'labels'])
                ->when($project, fn (Builder $query) => $query->where('project_id', $project->id))
                ->latest()
                ->get()
                ->map($this->serialize(...)),
            'project' => $project === null ? null : [
                'key' => $project->key,
                'name' => $project->name,
                'links' => $project->links(),
            ],
        ]);
    }

    public function updateStatus(UpdateIssueStatusRequest $request, Issue $issue): RedirectResponse
    {
        $status = IssueStatus::from($request->validated('status'));

        $issue->forceFill([
            'status' => $status,
            'closed_at' => $status === IssueStatus::Done ? now() : null,
        ])->save();

        return back();
    }

    private function findParent(mixed $parentId): ?Issue
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        return Issue::query()->where('id', $parentId)->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eligibleParents(?Issue $excluding = null): array
    {
        return Issue::query()
            ->whereNull('parent_id')
            ->when($excluding, fn ($query) => $query->where('id', '!=', $excluding->id))
            ->notArchived()
            ->orderBy('identifier')
            ->get(['id', 'identifier', 'title'])
            ->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'identifier' => $issue->identifier,
                'title' => $issue->title,
            ])
            ->all();
    }

    private function githubRepoBase(Issue $issue): ?string
    {
        if ($issue->github_pr_url !== null
            && preg_match('#^(https?://github\.com/[^/]+/[^/]+)/pull/#', $issue->github_pr_url, $matches)) {
            return $matches[1];
        }

        return $issue->project->primaryRepoBase();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Issue $issue): array
    {
        $repoBase = $this->githubRepoBase($issue);

        return [
            'identifier' => $issue->identifier,
            'title' => $issue->title,
            'description' => $issue->description,
            'type' => $issue->type->value,
            'priority' => $issue->priority->value,
            'status' => $issue->status->value,
            'branchName' => $issue->branch_name,
            'branchUrl' => $repoBase !== null ? $repoBase.'/tree/'.$issue->branch_name : null,
            'commitsUrl' => $repoBase !== null ? $repoBase.'/commits/'.$issue->branch_name : null,
            'githubPrUrl' => $issue->github_pr_url,
            'project' => [
                'key' => $issue->project->key,
                'name' => $issue->project->name,
            ],
            'createdAt' => $issue->created_at?->toIso8601String(),
            'archivedAt' => $issue->archived_at?->toIso8601String(),
            'childrenCount' => $issue->children_count
                ?? ($issue->relationLoaded('children') ? $issue->children->count() : 0),
            'parent' => $issue->relationLoaded('parent') && $issue->parent !== null ? [
                'id' => $issue->parent->id,
                'identifier' => $issue->parent->identifier,
                'title' => $issue->parent->title,
            ] : null,
            'children' => $issue->relationLoaded('children') ? $issue->children->map(fn (Issue $child) => [
                'identifier' => $child->identifier,
                'title' => $child->title,
                'status' => $child->status->value,
            ])->all() : [],
            'labels' => $issue->relationLoaded('labels') ? $issue->labels->map(fn (Label $label) => [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color->value,
            ])->all() : [],
        ];
    }
}
