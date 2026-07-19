<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectLevel;
use App\Models\Issue;
use App\Models\User;

class IssuePolicy
{
    public function view(User $user, Issue $issue): bool
    {
        return $this->atLeast($user, $issue, ProjectLevel::Read);
    }

    public function update(User $user, Issue $issue): bool
    {
        return $this->atLeast($user, $issue, ProjectLevel::Write);
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $this->atLeast($user, $issue, ProjectLevel::Admin);
    }

    private function atLeast(User $user, Issue $issue, ProjectLevel $level): bool
    {
        if (! ($issue->project->effectiveLevel($user)?->atLeast($level) ?? false)) {
            return false;
        }

        if ($issue->project->restrictsToOwnIssues($user)
            && $issue->owner_id !== $user->id
            && $issue->assignee_id !== $user->id) {
            return false;
        }

        return true;
    }
}
