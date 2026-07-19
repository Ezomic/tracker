<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueParentRequest;
use App\Http\Requests\UpdateIssueStatusRequest;
use App\Models\Issue;
use App\Models\IssueTemplate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IssueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project' => ['sometimes', 'string', 'exists:projects,key'],
        ]);

        $query = Issue::query()->visibleTo($request->user())->notArchived()->with(['project', 'parent', 'owner', 'assignee']);

        if (isset($validated['project'])) {
            $query->whereRelation('project', 'key', $validated['project']);
        }

        $issues = $query->orderBy('project_id')->orderBy('number')->get();

        return response()->json($issues->map(fn (Issue $issue): array => $this->summary($issue))->all());
    }

    public function show(Issue $issue): JsonResponse
    {
        $this->authorize('view', $issue);

        return response()->json($this->detail($issue->load(['project', 'parent', 'owner', 'assignee'])));
    }

    public function store(StoreIssueRequest $request, CreateIssueAction $action): JsonResponse
    {
        $project = Project::where('key', $request->validated('project'))->firstOrFail();

        $this->authorize('createIssue', $project);

        $template = $this->resolveTemplate($project, $request->validated('template'));

        $issue = $action->handle(
            project: $project,
            title: $request->validated('title'),
            type: IssueType::from($request->validated('type')),
            description: $request->validated('description') ?? $template?->description,
            parent: $this->resolveParent($request->validated('parent')),
            owner: $request->user(),
            assignee: $this->resolveAssignee($request->validated('assignee')),
            priority: $template?->priority,
        );

        if ($template !== null) {
            $issue->labels()->sync($template->labels->pluck('id')->all());
        }

        return response()->json($this->payload($issue), 201);
    }

    private function resolveTemplate(Project $project, ?string $name): ?IssueTemplate
    {
        if ($name === null) {
            return null;
        }

        return $project->organization
            ->issueTemplates()
            ->with('labels')
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->first();
    }

    public function update(UpdateIssueParentRequest $request, Issue $issue): JsonResponse
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
            $attributes['parent_id'] = $this->resolveParent($request->validated('parent'))?->id;
        }

        $issue->forceFill($attributes)->save();

        return response()->json($this->payload($issue->fresh()));
    }

    public function updateStatus(UpdateIssueStatusRequest $request, Issue $issue): JsonResponse
    {
        $this->authorize('update', $issue);

        $status = IssueStatus::from($request->validated('status'));

        $issue->forceFill([
            'status' => $status,
            'closed_at' => $status === IssueStatus::Done ? now() : null,
        ])->save();

        return response()->json($this->payload($issue->fresh()));
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
        ];
    }

    /**
     * Compact shape for list responses.
     *
     * @return array<string, string|null>
     */
    private function summary(Issue $issue): array
    {
        return [
            'identifier' => $issue->identifier,
            'title' => $issue->title,
            'type' => $issue->type->value,
            'status' => $issue->status->value,
            'project' => $issue->project->key,
            'parent' => $issue->parent?->identifier,
            'url' => url("/issues/{$issue->identifier}"),
        ];
    }

    /**
     * Full shape for single-issue responses.
     *
     * @return array<string, string|int|null>
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
            'status' => $issue->status->value,
            'branch_name' => $issue->branch_name,
            'github_pr_url' => $issue->github_pr_url,
            'project' => $issue->project->key,
            'owner' => $issue->owner?->email,
            'assignee' => $issue->assignee?->email,
            'parent' => $issue->parent?->identifier,
            'url' => url("/issues/{$issue->identifier}"),
            'created_at' => $issue->created_at?->toIso8601String(),
            'closed_at' => $issue->closed_at?->toIso8601String(),
        ];
    }
}
