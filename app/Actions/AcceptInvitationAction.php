<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvitationAction
{
    /**
     * Attach the user to the project with the invited level and close the
     * invitation. Existing membership is left as-is rather than downgraded.
     */
    public function handle(Invitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $project = $invitation->project;

            if (! $project->hasMember($user)) {
                $project->members()->attach($user->id, [
                    'level' => $invitation->level->value,
                    'is_favorite' => true,
                ]);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();
        });
    }
}
