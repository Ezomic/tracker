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
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project' => ['sometimes', 'string', 'exists:projects,key'],
        ]);

        $query = Issue::query()->notArchived()->with(['project', 'parent']);

        if (isset($validated['project'])) {
            $query->whereRelation('project', 'key', $validated['project']);
        }

        $issues = $query->orderBy('project_id')->orderBy('number')->get();

        return response()->json($issues->map(fn (Issue $issue): array => $this->summary($issue))->all());
    }

    public function show(Issue $issue): JsonResponse
    {
        return response()->json($this->detail($issue->load(['project', 'parent'])));
    }

    public function store(StoreIssueRequest $request, CreateIssueAction $action): JsonResponse
    {
        $project = Project::where('key', $request->validated('project'))->firstOrFail();

        $issue = $action->handle(
            project: $project,
            title: $request->validated('title'),
            type: IssueType::from($request->validated('type')),
            description: $request->validated('description'),
            parent: $this->resolveParent($request->validated('parent')),
        );

        return response()->json($this->payload($issue), 201);
    }

    public function update(UpdateIssueParentRequest $request, Issue $issue): JsonResponse
    {
        $parent = $this->resolveParent($request->validated('parent'));

        $issue->forceFill(['parent_id' => $parent?->id])->save();

        return response()->json($this->payload($issue->fresh()));
    }

    public function updateStatus(UpdateIssueStatusRequest $request, Issue $issue): JsonResponse
    {
        $status = IssueStatus::from($request->validated('status'));

        $issue->forceFill([
            'status' => $status,
            'closed_at' => $status === IssueStatus::Done ? now() : null,
        ])->save();

        return response()->json($this->payload($issue->fresh()));
    }

    private function resolveParent(?string $identifier): ?Issue
    {
        return $identifier !== null
            ? Issue::where('identifier', $identifier)->firstOrFail()
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
            'branch_name' => $issue->branch_name,
            'parent' => $issue->parent?->identifier,
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
            'parent' => $issue->parent?->identifier,
            'url' => url("/issues/{$issue->identifier}"),
            'created_at' => $issue->created_at?->toIso8601String(),
            'closed_at' => $issue->closed_at?->toIso8601String(),
        ];
    }
}
