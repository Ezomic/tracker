<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectMemberRequest;
use App\Http\Requests\UpdateProjectMemberRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectMemberController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $members = $project->members()->orderBy('name')->get()->map(function (User $user): array {
            /** @var Pivot $pivot */
            $pivot = $user->getAttribute('pivot');

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'level' => (string) $pivot->getAttribute('level'),
                'ownIssuesOnly' => (bool) $pivot->getAttribute('own_issues_only'),
            ];
        });

        $canManage = $this->currentUser($request)->can('manageMembers', $project);

        return Inertia::render('projects/Members', [
            'project' => [
                'key' => $project->key,
                'name' => $project->name,
            ],
            'members' => $members,
            // Only managers add people, and only from the organization's roster.
            'assignable' => $canManage ? $this->assignableMembers($project) : [],
            'canManage' => $canManage,
            'currentUserId' => $this->currentUser($request)->id,
        ]);
    }

    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $user = $this->guardOrganizationMember($project, (int) $request->validated('user_id'));

        if ($project->hasMember($user)) {
            return back()->withErrors(['user_id' => __('They already have access to this project.')]);
        }

        $project->members()->attach($user->id, [
            'level' => $request->validated('level'),
            'is_favorite' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added.')]);

        return back();
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        $this->guardMember($project, $user);

        $attributes = ['level' => $request->validated('level')];

        if ($request->has('own_issues_only')) {
            $attributes['own_issues_only'] = $request->boolean('own_issues_only');
        }

        $project->members()->updateExistingPivot($user->id, $attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return back();
    }

    public function destroy(Project $project, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        $this->guardMember($project, $user);

        $project->members()->detach($user->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }

    /**
     * Organization members who aren't yet on the project.
     *
     * @return list<array<string, mixed>>
     */
    private function assignableMembers(Project $project): array
    {
        $organization = $project->organization;

        if ($organization === null) {
            return [];
        }

        $existingIds = $project->members()->pluck('users.id')->all();

        return array_values($organization->members()
            ->whereNotIn('users.id', $existingIds)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all());
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
