<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectLevel;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->effectiveLevel($user) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->atLeast($user, $project, ProjectLevel::Admin);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->atLeast($user, $project, ProjectLevel::Admin);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->atLeast($user, $project, ProjectLevel::Admin);
    }

    public function createIssue(User $user, Project $project): bool
    {
        return $this->atLeast($user, $project, ProjectLevel::Write);
    }

    private function atLeast(User $user, Project $project, ProjectLevel $level): bool
    {
        return $project->effectiveLevel($user)?->atLeast($level) ?? false;
    }
}
