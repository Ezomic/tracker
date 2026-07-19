<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ArchiveIssueAction;
use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Http\Requests\ArchiveIssueRequest;
use App\Http\Requests\FilterIssuesRequest;
use App\Http\Requests\StoreIssueWebRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\UpdateIssueStatusRequest;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Commit;
use App\Models\Issue;
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\CurrentOrganization;
use App\Support\Duration;
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
                ->withSum('timeEntries', 'minutes')
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

        $issue->load([
            'project', 'owner', 'assignee', 'parent', 'labels',
            'children' => fn ($query) => $query->orderBy('number'),
            'timeEntries' => fn ($query) => $query->with('user')->orderByDesc('spent_on')->orderByDesc('id'),
            'comments' => fn ($query) => $query->with('user')->orderBy('created_at')->orderBy('id'),
            'activities' => fn ($query) => $query->with('user')->orderBy('created_at')->orderBy('id'),
            'commits' => fn ($query) => $query->orderBy('committed_at')->orderBy('id'),
        ]);
        $issue->loadSum('timeEntries', 'minutes');

        return Inertia::render('issues/Show', [
            'issue' => $this->serialize($issue),
            'timeline' => $this->serializeTimeline($issue),
            'members' => $this->projectMembers($issue->project),
            'epics' => $this->eligibleParents($request->user(), $issue),
            'labels' => Label::query()->forProject($issue->project)->orderBy('name')->get(['id', 'name', 'color']),
            'canLogTime' => $request->user()->can('update', $issue),
            'canManageTime' => $request->user()->can('delete', $issue),
            'canModerateComments' => $request->user()->can('delete', $issue),
            'canArchive' => $request->user()->can('update', $issue),
            'currentUserId' => $request->user()->id,
        ]);
    }

    /**
     * The issue's comments, activity events and commits, merged chronologically.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeTimeline(Issue $issue): array
    {
        $comments = $issue->comments->map(fn (Comment $comment): array => [
            'kind' => 'comment',
            'id' => $comment->id,
            'createdAt' => $comment->created_at->toIso8601String(),
            'user' => $this->serializeUser($comment->user),
            'body' => $comment->body,
        ]);

        $activities = $issue->activities->map(fn (Activity $activity): array => [
            'kind' => 'activity',
            'id' => $activity->id,
            'createdAt' => $activity->created_at->toIso8601String(),
            'user' => $this->serializeUser($activity->user),
            'type' => $activity->type,
            'data' => $activity->data,
        ]);

        $commits = $issue->commits->map(fn (Commit $commit): array => [
            'kind' => 'commit',
            'id' => $commit->id,
            'createdAt' => $commit->committed_at->toIso8601String(),
            'sha' => $commit->sha,
            'shortSha' => substr($commit->sha, 0, 7),
            'message' => $commit->message,
            'url' => $commit->url,
            'authorName' => $commit->author_name,
        ]);

        return $comments->concat($activities)->concat($commits)
            ->sortBy('createdAt')
            ->values()
            ->all();
    }

    public function update(UpdateIssueRequest $request, Issue $issue): RedirectResponse
    {
        $this->authorize('update', $issue);

        $issue->update($request->safe()->except(['labels', 'estimate']));
        $issue->labels()->sync($request->validated('labels', []));

        if ($request->has('estimate')) {
            $issue->forceFill(['estimate_minutes' => Duration::toMinutes($request->validated('estimate'))])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue updated.')]);

        return to_route('issues.show', $issue);
    }

    public function board(Request $request, CurrentOrganization $current, ?Project $project = null): Response
    {
        if ($project !== null) {
            $this->authorize('view', $project);
        }

        $showArchived = $request->boolean('archived');

        return Inertia::render('issues/Board', [
            'issues' => Issue::query()
                ->visibleTo($request->user())
                ->inOrganization($current->for($request->user()))
                ->when(! $showArchived, fn (Builder $query) => $query->notArchived())
                ->withCount('children')
                ->withSum('timeEntries', 'minutes')
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
            'showArchived' => $showArchived,
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

    public function archive(ArchiveIssueRequest $request, Issue $issue, ArchiveIssueAction $action): RedirectResponse
    {
        $this->authorize('update', $issue);

        $action->handle($issue, $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue archived.')]);

        return back();
    }

    public function unarchive(Issue $issue, ArchiveIssueAction $action): RedirectResponse
    {
        $this->authorize('update', $issue);

        $action->unarchive($issue);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue unarchived.')]);

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
            'estimateMinutes' => $issue->estimate_minutes,
            'loggedMinutes' => (int) ($issue->getAttribute('time_entries_sum_minutes') ?? 0),
            'invoiceable' => $issue->invoiceable,
            'confirmedMinutes' => $issue->confirmed_minutes,
            'confirmedAt' => $issue->confirmed_at?->toIso8601String(),
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
                'billrLinked' => $issue->project->billrLinked(),
            ],
            'owner' => $this->serializeUser($issue->relationLoaded('owner') ? $issue->owner : null),
            'assignee' => $this->serializeUser($issue->relationLoaded('assignee') ? $issue->assignee : null),
            'createdAt' => $issue->created_at?->toIso8601String(),
            'archivedAt' => $issue->archived_at?->toIso8601String(),
            'archiveReason' => $issue->archive_reason,
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
            'timeEntries' => $issue->relationLoaded('timeEntries') ? $issue->timeEntries->map(fn (TimeEntry $entry) => [
                'id' => $entry->id,
                'minutes' => $entry->minutes,
                'note' => $entry->note,
                'spentOn' => $entry->spent_on->toDateString(),
                'user' => $this->serializeUser($entry->relationLoaded('user') ? $entry->user : null),
            ])->all() : [],
            'comments' => $issue->relationLoaded('comments') ? $issue->comments->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'createdAt' => $comment->created_at->toIso8601String(),
                'user' => $this->serializeUser($comment->relationLoaded('user') ? $comment->user : null),
            ])->all() : [],
        ];
    }
}
