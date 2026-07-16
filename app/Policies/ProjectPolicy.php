<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->roleFor($user)?->manages() ?? false;
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $project->roleFor($user)?->manages() ?? false;
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->roleFor($user) === ProjectRole::Owner;
    }

    public function createIssue(User $user, Project $project): bool
    {
        return $project->hasMember($user);
    }
}
