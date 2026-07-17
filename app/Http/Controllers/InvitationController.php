<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AcceptInvitationAction;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(Request $request, string $token, AcceptInvitationAction $action): Response|RedirectResponse
    {
        $invitation = Invitation::query()
            ->with(['organization', 'project', 'invitedBy'])
            ->where('token', Invitation::hashToken($token))
            ->first();

        if ($invitation === null) {
            return $this->page('invalid');
        }

        if ($invitation->accepted_at !== null) {
            return $this->page('accepted', $invitation);
        }

        if ($invitation->isExpired()) {
            return $this->page('expired', $invitation);
        }

        $user = $request->user();

        if ($user === null) {
            // Send them back here once they've signed in or registered.
            $request->session()->put('url.intended', $request->url());

            return $this->page('guest', $invitation, [
                'hasAccount' => User::query()->where('email', $invitation->email)->exists(),
            ]);
        }

        if ($user->email !== $invitation->email) {
            return $this->page('mismatch', $invitation, [
                'currentEmail' => $user->email,
            ]);
        }

        $action->handle($invitation, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('You joined :organization.', ['organization' => $invitation->organization->name]),
        ]);

        return $invitation->project === null
            ? to_route('dashboard')
            : to_route('projects.board', $invitation->project->key);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function page(string $state, ?Invitation $invitation = null, array $extra = []): Response
    {
        return Inertia::render('auth/Invitation', [
            'state' => $state,
            'invitation' => $invitation === null ? null : [
                'email' => $invitation->email,
                'roleLabel' => $invitation->role->label(),
                'organizationName' => $invitation->organization->name,
                'projectName' => $invitation->project?->name,
                'inviterName' => $invitation->invitedBy?->name,
            ],
            ...$extra,
        ]);
    }
}
