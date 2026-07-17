<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendOrganizationInvitationAction;
use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Http\Requests\StoreOrganizationInvitationRequest;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OrganizationInvitationController extends Controller
{
    public function store(StoreOrganizationInvitationRequest $request, CurrentOrganization $current, SendOrganizationInvitationAction $action): RedirectResponse
    {
        $organization = $this->currentOrganization($request, $current);
        $this->authorize('manageMembers', $organization);

        $email = Str::lower($request->validated('email'));
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && $organization->hasMember($existing)) {
            return back()->withErrors(['email' => 'That person is already a member of this organization.']);
        }

        $project = $this->resolveProject($organization, $request->validated('project_id'));

        $action->handle(
            $organization,
            $email,
            OrganizationRole::from($request->validated('role')),
            $project,
            $project === null ? null : ProjectLevel::from($request->validated('level')),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    public function resend(Request $request, Invitation $invitation, CurrentOrganization $current, SendOrganizationInvitationAction $action): RedirectResponse
    {
        $organization = $this->currentOrganization($request, $current);
        $this->authorize('manageMembers', $organization);
        $this->guardBelongsToOrganization($organization, $invitation);

        $action->handle(
            $organization,
            $invitation->email,
            $invitation->role,
            $invitation->project,
            $invitation->level,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation resent.')]);

        return back();
    }

    public function destroy(Request $request, Invitation $invitation, CurrentOrganization $current): RedirectResponse
    {
        $organization = $this->currentOrganization($request, $current);
        $this->authorize('manageMembers', $organization);
        $this->guardBelongsToOrganization($organization, $invitation);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return back();
    }

    private function currentOrganization(Request $request, CurrentOrganization $current): Organization
    {
        $organization = $current->for($request->user());

        abort_if($organization === null, 404);

        return $organization;
    }

    private function resolveProject(Organization $organization, mixed $projectId): ?Project
    {
        if ($projectId === null) {
            return null;
        }

        return $organization->projects()->whereKey($projectId)->firstOrFail();
    }

    private function guardBelongsToOrganization(Organization $organization, Invitation $invitation): void
    {
        abort_unless($invitation->organization_id === $organization->id, 404);
    }
}
