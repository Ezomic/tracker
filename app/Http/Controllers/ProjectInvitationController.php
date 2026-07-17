<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendProjectInvitationAction;
use App\Enums\ProjectLevel;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProjectInvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, Project $project, SendProjectInvitationAction $action): RedirectResponse
    {
        $this->authorize('manageMembers', $project);

        $email = Str::lower($request->validated('email'));
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && $project->hasMember($existing)) {
            return back()->withErrors(['email' => 'That person is already a member of this project.']);
        }

        $action->handle(
            $project,
            $email,
            ProjectLevel::from($request->validated('level')),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    public function resend(Request $request, Project $project, Invitation $invitation, SendProjectInvitationAction $action): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        $this->guardBelongsToProject($project, $invitation);

        $action->handle($project, $invitation->email, $invitation->level, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation resent.')]);

        return back();
    }

    public function destroy(Project $project, Invitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $project);
        $this->guardBelongsToProject($project, $invitation);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return back();
    }

    private function guardBelongsToProject(Project $project, Invitation $invitation): void
    {
        abort_unless($invitation->project_id === $project->id, 404);
    }
}
