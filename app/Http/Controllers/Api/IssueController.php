<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueRequest;
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
        );

        return response()->json([
            'identifier' => $issue->identifier,
            'url' => url("/issues/{$issue->identifier}"),
            'branch_name' => $issue->branch_name,
        ], 201);
    }
}
