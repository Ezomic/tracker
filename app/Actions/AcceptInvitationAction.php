<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvitationAction
{
    /**
     * Attach the user to the organization with the invited role and, when the
     * invitation carries a project grant, to that project with the invited
     * level. Existing memberships are left as-is rather than downgraded.
     */
    public function handle(Invitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $organization = $invitation->organization;

            if (! $organization->hasMember($user)) {
                $organization->members()->attach($user->id, ['role' => $invitation->role->value]);
            }

            $project = $invitation->project;

            if ($project !== null && $invitation->level !== null && ! $project->hasMember($user)) {
                $project->members()->attach($user->id, [
                    'level' => $invitation->level->value,
                    'is_favorite' => true,
                ]);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();
        });
    }
}
