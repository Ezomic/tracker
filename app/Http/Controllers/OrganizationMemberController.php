<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Requests\UpdateOrganizationMemberRequest;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationMemberController extends Controller
{
    public function index(Request $request, CurrentOrganization $current): Response
    {
        $organization = $this->currentOrganization($request, $current);
        $this->authorize('manageMembers', $organization);

        return Inertia::render('settings/Members', [
            'organization' => [
                'name' => $organization->name,
            ],
            'members' => $organization->members()->orderBy('name')->get()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->pivotRole($user)->value,
            ]),
            'invitations' => $this->pendingInvitations($organization),
            'projects' => $organization->projects()->orderBy('key')->get()->map(fn (Project $project): array => [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
            ]),
            'currentUserId' => $request->user()->id,
        ]);
    }

    public function update(UpdateOrganizationMemberRequest $request, User $user, CurrentOrganization $current): RedirectResponse
    {
        $organization = $this->currentOrganization($request, $current);
        $this->authorize('manageMembers', $organization);
        $this->guardManageable($request, $organization, $user);

        $organization->members()->updateExistingPivot($user->id, [
            'role' => $request->validated('role'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return back();
    }

    public function destroy(Request $request, User $user, CurrentOrganization $current): RedirectResponse
    {
        $organization = $this->currentOrganization($request, $current);
        $this->authorize('manageMembers', $organization);
        $this->guardManageable($request, $organization, $user);

        // Leaving the org means giving up every project grant inside it too.
        $organization->projects()->each(fn (Project $project) => $project->members()->detach($user->id));
        $organization->members()->detach($user->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }

    private function currentOrganization(Request $request, CurrentOrganization $current): Organization
    {
        $organization = $current->for($request->user());

        abort_if($organization === null, 404);

        return $organization;
    }

    private function pivotRole(User $user): OrganizationRole
    {
        /** @var Pivot $pivot */
        $pivot = $user->getAttribute('pivot');

        return OrganizationRole::from((string) $pivot->getAttribute('role'));
    }

    private function guardManageable(Request $request, Organization $organization, User $user): void
    {
        abort_unless($organization->hasMember($user), 404);
        // Owners are untouchable, and you can't manage your own membership here.
        abort_if($user->id === $request->user()->id, 403);
        abort_if($organization->roleFor($user) === OrganizationRole::Owner, 403);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingInvitations(Organization $organization): array
    {
        return array_values($organization->invitations()
            ->pending()
            ->with('project:id,key,name')
            ->orderBy('email')
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'projectName' => $invitation->project?->name,
                'level' => $invitation->level?->value,
                'expiresAt' => $invitation->expires_at->toIso8601String(),
            ])
            ->all());
    }
}
