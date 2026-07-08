<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Http\Requests\StoreIssueWebRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\UpdateIssueStatusRequest;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('issues/Index', [
            'issues' => Issue::query()
                ->notArchived()
                ->withCount('children')
                ->with(['team', 'labels'])
                ->latest()
                ->get()
                ->map($this->serialize(...)),
            'teams' => Team::query()->orderBy('key')->get(['id', 'key', 'name']),
            'epics' => $this->eligibleParents(),
            'labels' => Label::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(StoreIssueWebRequest $request, CreateIssueAction $action): RedirectResponse
    {
        $team = Team::where('id', $request->validated('team_id'))->firstOrFail();
        $parent = $this->findParent($request->validated('parent_id'));

        $issue = $action->handle(
            team: $team,
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
        $issue->load(['team', 'parent', 'labels', 'children' => fn ($query) => $query->orderBy('number')]);

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

    public function board(): Response
    {
        return Inertia::render('issues/Board', [
            'issues' => Issue::query()
                ->notArchived()
                ->withCount('children')
                ->with(['team', 'labels'])
                ->latest()
                ->get()
                ->map($this->serialize(...)),
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

    /**
     * @return array<string, mixed>
     */
    private function serialize(Issue $issue): array
    {
        return [
            'identifier' => $issue->identifier,
            'title' => $issue->title,
            'description' => $issue->description,
            'type' => $issue->type->value,
            'priority' => $issue->priority->value,
            'status' => $issue->status->value,
            'branchName' => $issue->branch_name,
            'githubPrUrl' => $issue->github_pr_url,
            'team' => [
                'key' => $issue->team->key,
                'name' => $issue->team->name,
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
