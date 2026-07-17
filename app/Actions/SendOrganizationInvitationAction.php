<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Mail\OrganizationInvitationMail;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendOrganizationInvitationAction
{
    private const EXPIRES_DAYS = 7;

    /**
     * Issue (or re-issue) an invitation to join an organization, optionally with
     * an initial grant on one project, and mail the link. Any existing invitation
     * for the same organization and email is refreshed with a new token and
     * expiry, so re-inviting invalidates the previous link.
     */
    public function handle(
        Organization $organization,
        string $email,
        OrganizationRole $role,
        ?Project $project = null,
        ?ProjectLevel $level = null,
        ?User $invitedBy = null,
    ): Invitation {
        $plainToken = Str::random(40);

        $invitation = Invitation::updateOrCreate(
            ['organization_id' => $organization->id, 'email' => Str::lower($email)],
            [
                'role' => $role,
                'project_id' => $project?->id,
                'level' => $project === null ? null : $level,
                'token' => Invitation::hashToken($plainToken),
                'invited_by_id' => $invitedBy?->id,
                'expires_at' => now()->addDays(self::EXPIRES_DAYS),
                'accepted_at' => null,
            ],
        );

        $invitation->setRelation('organization', $organization);
        $invitation->setRelation('project', $project);
        $invitation->setRelation('invitedBy', $invitedBy);

        Mail::to($invitation->email)->send(new OrganizationInvitationMail(
            $invitation,
            route('invitations.show', $plainToken),
        ));

        return $invitation;
    }
}
