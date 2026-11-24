<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;

class NotePolicy
{
    public function view(User $user, Note $note): bool
    {
        return $note->project->effectiveLevel($user) !== null;
    }

    public function create(User $user, Project $project): bool
    {
        return $this->atLeast($user, $project, ProjectLevel::Write);
    }

    public function update(User $user, Note $note): bool
    {
        return $this->atLeast($user, $note->project, ProjectLevel::Write);
    }

    public function delete(User $user, Note $note): bool
    {
        if ($this->atLeast($user, $note->project, ProjectLevel::Admin)) {
            return true;
        }

        return $note->user_id === $user->id
            && $this->atLeast($user, $note->project, ProjectLevel::Write);
    }

    /**
     * Guests are read-only on notes whatever they are granted, so a service
     * account holding write on a project for filing issues cannot edit notes.
     */
    private function atLeast(User $user, Project $project, ProjectLevel $level): bool
    {
        if ($project->organization?->roleFor($user) === OrganizationRole::Guest) {
            return false;
        }

        return $project->effectiveLevel($user)?->atLeast($level) ?? false;
    }
}
