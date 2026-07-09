<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueParentRequest;
use App\Models\Issue;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class IssueController extends Controller
{
    public function store(StoreIssueRequest $request, CreateIssueAction $action): JsonResponse
    {
        $team = Team::where('key', $request->validated('team'))->firstOrFail();

        $issue = $action->handle(
            team: $team,
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
}
