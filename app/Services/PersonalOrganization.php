<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Every account needs an organization to file projects under, so one is created
 * alongside the account.
 */
class PersonalOrganization
{
    public function create(User $user): Organization
    {
        $organization = Organization::create([
            'name' => $user->name,
            'slug' => Str::slug($user->name).'-'.Str::lower(Str::random(6)),
        ]);

        $organization->members()->attach($user->id, ['role' => OrganizationRole::Owner->value]);

        return $organization;
    }
}
