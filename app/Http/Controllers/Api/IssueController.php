<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AddCommentAction;
use App\Actions\ArchiveIssueAction;
use App\Actions\BulkUpdateIssuesAction;
use App\Actions\CreateIssueAction;
use App\Actions\LogTimeAction;
use App\Actions\MoveIssueToStateAction;
use App\Actions\ResolveWorkflowStateAction;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateIssuesRequest;
use App\Http\Requests\FilterIssuesApiRequest;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Requests\UpdateIssueApiRequest;
use App\Http\Requests\UpdateIssueStatusApiRequest;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\IssueTemplate;
use App\Models\Label;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkflowState;
use App\Support\Duration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IssueController extends Controller
{
    public function index(FilterIssuesApiRequest $request): JsonResponse
    {
        $issues = Issue::query()
            ->visibleTo($this->currentUser($request))
            ->when($request->archived() === 'exclude', fn (Builder $query) => $query->notArchived())
            ->when($request->archived() === 'only', fn (Builder $query) => $query->whereNotNull('archived_at'))
            ->with(['project', 'parent', 'assignee', 'labels', 'workflowState'])
            ->when($request->filled('project'), fn (Builder $query) => $query->whereRelation('project', 'key', $request->string('project')->toString()))
            ->when($request->filled('search'), fn (Builder $query) => $this->applySearch($query, $request->string('search')->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('priority'), fn (Builder $query) => $query->where('priority', $request->string('priority')->toString()))
            ->when($request->filled('label'), fn (Builder $query) => $query->whereHas('labels', fn (Builder $labels) => $labels->whereRaw('lower(labels.name) = ?', [Str::lower($request->string('label')->toString())])))
            ->when($request->filled('assignee'), fn (Builder $query) => $this->applyAssignee($query, $request->string('assignee')->toString()))
            ->when($request->filled('parent'), fn (Builder $query) => $query->whereRelation('parent', 'identifier', $request->string('parent')->toString()))
            ->when($request->filled('source'), fn (Builder $query) => $query->where('source', $request->string('source')->toString()))
            ->when($request->filled('workflow_state'), fn (Builder $query) => $query->whereHas('workflowState', fn (Builder $states) => $states->whereRaw('lower(name) = ?', [Str::lower($request->string('workflow_state')->toString())])))
            ->when($request->filled('state_category'), fn (Builder $query) => $query->whereHas('workflowState', fn (Builder $states) => $states->where('category', $request->string('state_category')->toString())))
            ->orderBy('project_id')
            ->orderBy('number')
            ->paginate($request->perPage())
            ->withQueryString();

        return response()->json([
            'data' => array_map($this->summary(...), $issues->items()),
            'meta' => [
                'total' => $issues->total(),
                'per_page' => $issues->perPage(),
                'current_page' => $issues->currentPage(),
                'last_page' => $issues->lastPage(),
            ],
        ]);
    }

    /**
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    private function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(fn (Builder $group) => $group
            ->where('title', 'like', '%'.$search.'%')
            ->orWhere('identifier', 'like', '%'.$search.'%')
            ->orWhere('description', 'like', '%'.$search.'%'));
    }

    /**
     * `none` selects unassigned issues; anything else is matched as an email.
     *
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    private function applyAssignee(Builder $query, string $assignee): Builder
    {
        if (Str::lower($assignee) === 'none') {
            return $query->whereNull('assignee_id');
        }

        return $query->whereRelation('assignee', 'email', Str::lower($assignee));
    }

    public function show(Issue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        return response()->json($this->detail($issue->load(['project', 'parent', 'owner', 'assignee', 'labels', 'workflowState'])));
    }

    public function store(StoreIssueRequest $request, CreateIssueAction $action): JsonResponse
    {
        $project = Project::where('key', $request->string('project')->toString())->firstOrFail();

        $this->authorize('createIssue', $project);

        $source = $request->string('source')->toString() ?: null;
        $externalRef = $request->string('external_ref')->toString() ?: null;

        // Automated filers retry. Handing back the issue they already filed,
        // rather than a second one, is what makes a retry safe.
        if ($source !== null && $externalRef !== null) {
            $existing = Issue::query()
                ->where('project_id', $project->id)
                ->where('source', $source)
                ->where('external_ref', $externalRef)
                ->first();

            if ($existing !== null) {
                return response()->json($this->payload($existing));
            }
        }

        $template = $this->resolveTemplate($project, $request->string('template')->toString() ?: null);
        $priority = $request->string('priority')->toString() ?: null;
        $estimate = $request->string('estimate')->toString() ?: null;
        $labels = $this->stringList($request->validated('labels'));

        $issue = $action->handle(
            project: $project,
            title: $request->string('title')->toString(),
            type: IssueType::from($request->string('type')->toString()),
            description: ($request->string('description')->toString() ?: null) ?? $template?->description,
            parent: $this->resolveParent($request->string('parent')->toString() ?: null),
            owner: $this->currentUser($request),
            assignee: $this->resolveAssignee($request->string('assignee')->toString() ?: null),
            priority: $priority !== null ? IssuePriority::from($priority) : $template?->priority,
            template: $template,
            source: $source,
            externalRef: $externalRef,
            externalReporter: $request->string('external_reporter')->toString() ?: null,
        );

        // Explicit labels replace the template's; the template only fills the gap.
        if ($labels !== []) {
            $issue->labels()->sync($this->resolveLabelIds($project, $labels));
        } elseif ($template !== null) {
            $issue->labels()->sync($template->labels->pluck('id')->all());
        }

        if ($estimate !== null) {
            $issue->forceFill([
                'estimate_minutes' => Duration::toMinutes($estimate),
            ])->save();
        }

        return response()->json($this->payload($issue), 201);
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function resolveLabelIds(Project $project, array $names): array
    {
        $wanted = array_map(fn (string $name): string => Str::lower($name), $names);
        $ids = [];

        foreach (Label::query()->forProject($project)->get(['id', 'name']) as $label) {
            if (in_array(Str::lower($label->name), $wanted, true)) {
                $ids[] = $label->id;
            }
        }

        return $ids;
    }

    private function resolveTemplate(Project $project, ?string $name): ?IssueTemplate
    {
        if ($name === null) {
            return null;
        }

        return $project->organization
            ?->issueTemplates()
            ->with('labels')
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->first();
    }

    public function update(UpdateIssueApiRequest $request, Issue $issue): JsonResponse
    {
        $this->authorize('update', $issue);

        $attributes = [];

        if ($request->has('title')) {
            $attributes['title'] = $request->validated('title');
        }

        if ($request->has('description')) {
            $attributes['description'] = $request->validated('description');
        }

        if ($request->has('parent')) {
            $attributes['parent_id'] = $this->resolveParent($request->string('parent')->toString() ?: null)?->id;
        }

        if ($request->has('type')) {
            $attributes['type'] = IssueType::from($request->string('type')->toString());
        }

        if ($request->has('priority')) {
            $attributes['priority'] = IssuePriority::from($request->string('priority')->toString());
        }

        if ($request->has('assignee')) {
            $attributes['assignee_id'] = $this->resolveAssignee($request->string('assignee')->toString() ?: null)?->id;
        }

        if ($request->has('estimate')) {
            $estimate = $request->string('estimate')->toString() ?: null;
            $attributes['estimate_minutes'] = $estimate === null ? null : Duration::toMinutes($estimate);
        }

        $issue->forceFill($attributes)->save();

        // Only touched when the key is present. An omitted `labels` leaves them
        // alone here, unlike the web endpoint where the whole form is submitted
        // and an omission means "none" — a partial patch must stay partial.
        if ($request->has('labels')) {
            $issue->syncLabelsWithActivity($this->resolveLabelIds($issue->project, $this->stringList($request->validated('labels', []))));
        }

        return response()->json(
            $this->detail($issue->refresh()->load(['project', 'parent', 'owner', 'assignee', 'labels', 'workflowState']))
        );
    }

    /**
     * Move an issue, by lane or by the legacy status.
     *
     * Both forms land in MoveIssueToStateAction so `status`, `workflow_state_id`
     * and `closed_at` cannot drift apart. Previously this wrote `status` alone
     * and left workflow_state_id pointing at the lane the issue used to be in,
     * so the board and the API disagreed about where it was.
     */
    /**
     * One request for a whole selection, so a caller does not have to loop and
     * burn the 60-per-minute write budget one issue at a time.
     */
    public function bulk(BulkUpdateIssuesRequest $request, BulkUpdateIssuesAction $action): JsonResponse
    {
        return response()->json(
            $action->handle($request->identifiers(), $request->changes(), $this->currentUser($request))
        );
    }

    public function updateStatus(UpdateIssueStatusApiRequest $request, Issue $issue, MoveIssueToStateAction $move, ResolveWorkflowStateAction $resolve): JsonResponse
    {
        $this->authorize('update', $issue);

        $state = $request->has('workflow_state')
            ? $request->resolveState()
            : $resolve->handle($issue->project, IssueStatus::from($request->string('status')->toString()));

        if ($state instanceof WorkflowState) {
            $move->handle($issue, $state);

            return response()->json($this->payload($issue->refresh()));
        }

        // A project with no type, or a type with no lane meaning this status,
        // still has to honour the request: fall back to writing status alone,
        // which is what this endpoint did before lanes existed.
        $status = IssueStatus::from($request->string('status')->toString());

        $issue->forceFill([
            'status' => $status,
            'closed_at' => $status === IssueStatus::Done ? now() : null,
        ])->save();

        return response()->json($this->payload($issue->refresh()));
    }

    public function listComments(Request $request, Issue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        $comments = $issue->comments()
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'body' => $comment->body,
                'user' => $comment->user?->name,
                'createdAt' => $comment->created_at->toIso8601String(),
                'editedAt' => $comment->edited_at?->toIso8601String(),
            ]);

        return response()->json($comments);
    }

    public function storeComment(StoreCommentRequest $request, Issue $issue, AddCommentAction $action): JsonResponse
    {
        // Anyone who can see the issue can comment on it.
        $this->authorize('view', $issue);

        $comment = $action->handle($issue, $this->currentUser($request), $request->string('body')->toString());

        return response()->json([
            'id' => $comment->id,
            'body' => $comment->body,
            'user' => $this->currentUser($request)->name,
            'createdAt' => $comment->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * Only the author may edit, matching the web route. No notification is sent:
     * an edit must not become a way to ping someone after the fact.
     */
    public function updateComment(UpdateCommentRequest $request, Issue $issue, Comment $comment): JsonResponse
    {
        abort_unless($comment->issue_id === $issue->id, 404);
        $this->authorize('view', $issue);
        abort_unless($comment->user_id === $this->currentUser($request)->id, 403);

        $comment->forceFill([
            'body' => $request->string('body')->toString(),
            'edited_at' => now(),
        ])->save();

        return response()->json([
            'id' => $comment->id,
            'body' => $comment->body,
            'user' => $comment->user?->name,
            'createdAt' => $comment->created_at->toIso8601String(),
            'editedAt' => $comment->edited_at?->toIso8601String(),
        ]);
    }

    public function listTime(Request $request, Issue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        $entries = $issue->timeEntries()
            ->with('user:id,name')
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TimeEntry $entry): array => [
                'id' => $entry->id,
                'minutes' => $entry->minutes,
                'spentOn' => $entry->spent_on->toDateString(),
                'note' => $entry->note,
                'user' => $entry->user?->name,
            ]);

        return response()->json($entries);
    }

    public function deleteTime(Request $request, Issue $issue, TimeEntry $timeEntry): JsonResponse
    {
        abort_unless($timeEntry->issue_id === $issue->id, 404);

        // You can always remove your own entry; otherwise it takes project admin.
        if ($timeEntry->user_id !== $this->currentUser($request)->id) {
            $this->authorize('delete', $issue);
        } else {
            $this->authorize('view', $issue);
        }

        $timeEntry->delete();

        return response()->json(status: 204);
    }

    public function logTime(StoreTimeEntryRequest $request, Issue $issue, LogTimeAction $action): JsonResponse
    {
        $this->authorize('update', $issue);

        $entry = $action->handle(
            $issue,
            $this->currentUser($request),
            $request->string('duration')->toString(),
            $request->string('spent_on')->toString() ?: null,
            $request->string('note')->toString() ?: null,
        );

        return response()->json([
            'id' => $entry->id,
            'issue' => $issue->identifier,
            'minutes' => $entry->minutes,
            'spentOn' => $entry->spent_on->toDateString(),
            'note' => $entry->note,
        ], 201);
    }

    public function destroy(Request $request, Issue $issue): JsonResponse
    {
        $this->authorize('delete', $issue);

        $reason = $request->string('reason')->trim()->limit(500)->value();

        if ($issue->archived_at === null) {
            $issue->forceFill([
                'archived_at' => now(),
                'archive_reason' => $reason !== '' ? $reason : null,
            ])->save();
        }

        return response()->json([
            'identifier' => $issue->identifier,
            'url' => url("/issues/{$issue->identifier}"),
            'archived_at' => $issue->archived_at?->toIso8601String(),
            'archive_reason' => $issue->archive_reason,
        ]);
    }

    /**
     * The inverse of destroy(), and gated on the same ability: whoever can
     * archive an issue can undo it, and nobody else can resurrect what an
     * admin archived.
     */
    public function restore(Issue $issue, ArchiveIssueAction $action): JsonResponse
    {
        $this->authorize('delete', $issue);

        $action->unarchive($issue);

        return response()->json([
            'identifier' => $issue->identifier,
            'url' => url("/issues/{$issue->identifier}"),
            'archived_at' => null,
        ]);
    }

    private function resolveParent(?string $identifier): ?Issue
    {
        return $identifier !== null
            ? Issue::where('identifier', $identifier)->firstOrFail()
            : null;
    }

    private function resolveAssignee(?string $email): ?User
    {
        return $email !== null
            ? User::query()->where('email', Str::lower($email))->first()
            : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function payload(Issue $issue): array
    {
        return [
            'identifier' => $issue->identifier,
            'url' => url("/issues/{$issue->identifier}"),
            'title' => $issue->title,
            'description' => $issue->description,
            'branch_name' => $issue->branch_name,
            'parent' => $issue->parent?->identifier,
            'owner' => $issue->owner?->email,
            'assignee' => $issue->assignee?->email,
            'source' => $issue->source,
            'external_ref' => $issue->external_ref,
            'external_reporter' => $issue->external_reporter,
        ];
    }

    /**
     * The lane an issue sits in. Null for a project with no type, which is why
     * `status` stays populated for now rather than being replaced outright.
     *
     * @return array<string, mixed>|null
     */
    private function statePayload(Issue $issue): ?array
    {
        $state = $issue->workflowState;

        return $state === null ? null : [
            'id' => $state->id,
            'name' => $state->name,
            'category' => $state->category->value,
        ];
    }

    /**
     * Compact shape for list responses.
     *
     * @return array<string, mixed>
     */
    private function summary(Issue $issue): array
    {
        return [
            'identifier' => $issue->identifier,
            'title' => $issue->title,
            'type' => $issue->type->value,
            // Deprecated, sunsets 2026-09-30: read workflow_state instead.
            'status' => $issue->status->value,
            'workflow_state' => $this->statePayload($issue),
            'priority' => $issue->priority->value,
            'project' => $issue->project->key,
            'parent' => $issue->parent?->identifier,
            'assignee' => $issue->assignee?->email,
            'estimate_minutes' => $issue->estimate_minutes,
            'labels' => $issue->labels->pluck('name')->all(),
            'url' => url("/issues/{$issue->identifier}"),
            'created_at' => $issue->created_at?->toIso8601String(),
            'closed_at' => $issue->closed_at?->toIso8601String(),
            'archived_at' => $issue->archived_at?->toIso8601String(),
            'source' => $issue->source,
        ];
    }

    /**
     * Full shape for single-issue responses.
     *
     * @return array<string, mixed>
     */
    private function detail(Issue $issue): array
    {
        return [
            'identifier' => $issue->identifier,
            'number' => $issue->number,
            'title' => $issue->title,
            'description' => $issue->description,
            'type' => $issue->type->value,
            'priority' => $issue->priority->value,
            // Deprecated, sunsets 2026-09-30: read workflow_state instead.
            'status' => $issue->status->value,
            'workflow_state' => $this->statePayload($issue),
            'estimate_minutes' => $issue->estimate_minutes,
            'labels' => $issue->labels->pluck('name')->all(),
            'branch_name' => $issue->branch_name,
            'github_pr_url' => $issue->github_pr_url,
            'project' => $issue->project->key,
            'owner' => $issue->owner?->email,
            'assignee' => $issue->assignee?->email,
            'parent' => $issue->parent?->identifier,
            'url' => url("/issues/{$issue->identifier}"),
            'created_at' => $issue->created_at?->toIso8601String(),
            'closed_at' => $issue->closed_at?->toIso8601String(),
            'archived_at' => $issue->archived_at?->toIso8601String(),
            'archive_reason' => $issue->archive_reason,
            'source' => $issue->source,
            'external_ref' => $issue->external_ref,
            'external_reporter' => $issue->external_reporter,
            'watchers' => $issue->watchers()->wherePivot('watching', true)->count(),
        ];
    }
}
