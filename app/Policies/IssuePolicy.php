<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;

class IssuePolicy
{
    public function view(User $user, Issue $issue): bool
    {
        return $issue->project->hasMember($user);
    }

    public function update(User $user, Issue $issue): bool
    {
        return $issue->project->hasMember($user);
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $issue->project->roleFor($user)?->manages() ?? false;
    }
}
