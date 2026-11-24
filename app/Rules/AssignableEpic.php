<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class AssignableEpic
{
    /**
     * A top-level issue the user can see, in any project of the given
     * organization. Epics may span projects (one SSO epic covers several apps),
     * but never organizations.
     */
    public static function in(?int $organizationId, User $user): Exists
    {
        return Rule::exists('issues', 'id')
            ->where('parent_id', null)
            ->where(fn (Builder $query) => $query
                ->whereIn('project_id', Project::query()->select('id')->where('organization_id', $organizationId))
                ->whereIn('id', Issue::query()->visibleTo($user)->select('issues.id')));
    }
}
