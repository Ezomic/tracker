<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProjectRole;
use App\Mail\ProjectInvitationMail;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendProjectInvitationAction
{
    private const EXPIRES_DAYS = 7;

    /**
     * Issue (or re-issue) an invitation for an email and mail the link. Any
     * existing invitation for the same project and email is refreshed with a
     * new token and expiry, so re-inviting invalidates the previous link.
     */
    public function handle(Project $project, string $email, ProjectRole $role, ?User $invitedBy = null): Invitation
    {
        $plainToken = Str::random(40);

        $invitation = Invitation::updateOrCreate(
            ['project_id' => $project->id, 'email' => Str::lower($email)],
            [
                'role' => $role,
                'token' => Invitation::hashToken($plainToken),
                'invited_by_id' => $invitedBy?->id,
                'expires_at' => now()->addDays(self::EXPIRES_DAYS),
                'accepted_at' => null,
            ],
        );

        $invitation->setRelation('project', $project);
        $invitation->setRelation('invitedBy', $invitedBy);

        Mail::to($invitation->email)->send(new ProjectInvitationMail(
            $invitation,
            route('invitations.show', $plainToken),
        ));

        return $invitation;
    }
}
