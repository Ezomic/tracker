<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProjectMemberRequest;
use App\Models\Invitation;
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
            ];
        });

        $canManage = $request->user()->can('manageMembers', $project);

        return Inertia::render('projects/Members', [
            'project' => [
                'key' => $project->key,
                'name' => $project->name,
            ],
            'members' => $members,
            // Pending invitations are only the managers' business.
            'invitations' => $canManage ? $this->pendingInvitations($project) : [],
            'canManage' => $canManage,
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        $this->guardMember($project, $user);

        $project->members()->updateExistingPivot($user->id, [
            'level' => $request->validated('level'),
        ]);

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
     * @return list<array<string, mixed>>
     */
    private function pendingInvitations(Project $project): array
    {
        return array_values($project->invitations()
            ->pending()
            ->orderBy('email')
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'level' => $invitation->level->value,
                'expiresAt' => $invitation->expires_at->toIso8601String(),
            ])
            ->all());
    }

    private function guardMember(Project $project, User $user): void
    {
        abort_unless($project->hasMember($user), 404);
    }
}
