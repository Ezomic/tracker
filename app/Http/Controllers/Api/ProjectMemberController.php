<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectMemberRequest;
use App\Http\Requests\UpdateProjectMemberRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\JsonResponse;

class ProjectMemberController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $members = $project->members()->orderBy('name')->get()->map(function (User $user): array {
            /** @var Pivot $pivot */
            $pivot = $user->getAttribute('pivot');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'level' => Cast::string($pivot->getAttribute('level')),
                'ownIssuesOnly' => (bool) $pivot->getAttribute('own_issues_only'),
            ];
        });

        return response()->json($members);
    }

    public function store(StoreProjectMemberRequest $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $user = $this->guardOrganizationMember($project, $request->integer('user_id'));

        if ($project->hasMember($user)) {
            return response()->json(['message' => 'They already have access to this project.'], 422);
        }

        $project->members()->attach($user->id, [
            'level' => $request->validated('level'),
            'is_favorite' => false,
        ]);

        return response()->json(['id' => $user->id, 'level' => $request->string('level')->toString()], 201);
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $project);
        $this->guardMember($project, $user);

        $attributes = ['level' => $request->validated('level')];

        if ($request->has('own_issues_only')) {
            $attributes['own_issues_only'] = $request->boolean('own_issues_only');
        }

        $project->members()->updateExistingPivot($user->id, $attributes);

        return response()->json([
            'id' => $user->id,
            'level' => $request->string('level')->toString(),
            'ownIssuesOnly' => $request->boolean('own_issues_only'),
        ]);
    }

    public function destroy(Project $project, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $project);
        $this->guardMember($project, $user);

        $project->members()->detach($user->id);

        return response()->json(status: 204);
    }

    private function guardOrganizationMember(Project $project, int $userId): User
    {
        $user = User::query()->findOrFail($userId);

        abort_unless($project->organization?->hasMember($user) ?? false, 404);

        return $user;
    }

    private function guardMember(Project $project, User $user): void
    {
        abort_unless($project->hasMember($user), 404);
    }
}
