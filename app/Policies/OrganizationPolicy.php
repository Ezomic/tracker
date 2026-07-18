<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $organization->hasMember($user);
    }

    /**
     * See the shared library (templates, labels). Everyone in the org except
     * guests, who are sandboxed to the projects they're granted.
     */
    public function viewLibrary(User $user, Organization $organization): bool
    {
        $role = $organization->roleFor($user);

        return $role !== null && $role !== OrganizationRole::Guest;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $organization->roleFor($user)?->manages() ?? false;
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $organization->roleFor($user)?->manages() ?? false;
    }
}
