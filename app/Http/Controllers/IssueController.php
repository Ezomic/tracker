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
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;
use App\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function index(FilterIssuesRequest $request, CurrentOrganization $current, ?Project $project = null): Response
    {
        $user = $request->user();
        $organization = $current->for($user);
        $filters = $request->validated();

        if ($project !== null) {
            $this->authorize('view', $project);
            $filters['project_id'] = $project->id;
        }

        return Inertia::render('issues/Index', [
            'issues' => Issue::query()
                ->visibleTo($user)
                ->inOrganization($organization)
                ->notArchived()
                ->withCount('children')
                ->with(['project', 'labels', 'assignee'])
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
                ->visibleTo($user)
                ->inOrganization($organization)
                ->orderBy('key')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'key' => $project->key,
                    'name' => $project->name,
                    'color' => $project->color,
                    'links' => $project->links(),
                ]),
            'epics' => $this->eligibleParents($user),
            'labels' => Label::query()->forOrganization($organization)->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => $filters,
        ]);
    }

    public function store(StoreIssueWebRequest $request, CreateIssueAction $action): RedirectResponse
    {
        $project = Project::where('id', $request->validated('project_id'))->firstOrFail();

        $this->authorize('createIssue', $project);

        $parent = $this->findParent($request->validated('parent_id'));
        // Title, type and description are prefilled client-side and editable;
        // priority and labels have no field on the form, so they come straight
        // off the template here.
        $template = $this->findTemplate($request->validated('template_id'));

        $issue = $action->handle(
            project: $project,
            title: $request->validated('title'),
            type: IssueType::from($request->validated('type')),
            description: $request->validated('description'),
            parent: $parent,
            owner: $request->user(),
            priority: $template?->priority,
        );

        if ($template !== null) {
            $issue->labels()->sync($template->labels->pluck('id')->all());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue created.')]);

        return to_route('issues.show', $issue);
    }

    public function show(Request $request, Issue $issue): Response
    {
        $this->authorize('view', $issue);

        $issue->load(['project', 'owner', 'assignee', 'parent', 'labels', 'children' => fn ($query) => $query->orderBy('number')]);

        return Inertia::render('issues/Show', [
            'issue' => $this->serialize($issue),
            'members' => $this->projectMembers($issue->project),
            'epics' => $this->eligibleParents($request->user(), $issue),
            'labels' => Label::query()->forProject($issue->project)->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function update(UpdateIssueRequest $request, Issue $issue): RedirectResponse
    {
        $this->authorize('update', $issue);

        $issue->update($request->safe()->except('labels'));
        $issue->labels()->sync($request->validated('labels', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue updated.')]);

        return to_route('issues.show', $issue);
    }

    public function board(Request $request, CurrentOrganization $current, ?Project $project = null): Response
    {
        if ($project !== null) {
            $this->authorize('view', $project);
        }

        return Inertia::render('issues/Board', [
            'issues' => Issue::query()
                ->visibleTo($request->user())
                ->inOrganization($current->for($request->user()))
                ->notArchived()
                ->withCount('children')
                ->with(['project', 'labels', 'assignee'])
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
        $this->authorize('update', $issue);

        $status = IssueStatus::from($request->validated('status'));

        $issue->forceFill([
            'status' => $status,
            'closed_at' => $status === IssueStatus::Done ? now() : null,
        ])->save();

        return back();
    }

    private function findTemplate(mixed $templateId): ?IssueTemplate
    {
        return $templateId === null
            ? null
            : IssueTemplate::query()->with('labels')->whereKey((int) $templateId)->first();
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
    private function eligibleParents(User $user, ?Issue $excluding = null): array
    {
        return Issue::query()
            ->visibleTo($user)
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
     * @return array<string, mixed>|null
     */
    private function serializeUser(?User $user): ?array
    {
        return $user === null ? null : [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projectMembers(Project $project): array
    {
        return array_values($project->members()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all());
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
            'owner' => $this->serializeUser($issue->relationLoaded('owner') ? $issue->owner : null),
            'assignee' => $this->serializeUser($issue->relationLoaded('assignee') ? $issue->assignee : null),
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
