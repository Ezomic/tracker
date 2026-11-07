<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\SeedOrganizationStarterDataAction;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Every account needs an organization to file projects under, so one is created
 * alongside the account, with the starter labels, templates and project type.
 */
class PersonalOrganization
{
    public function __construct(private readonly SeedOrganizationStarterDataAction $starterData) {}

    public function create(User $user): Organization
    {
        $organization = Organization::create([
            'name' => $user->name,
            'slug' => Str::slug($user->name).'-'.Str::lower(Str::random(6)),
        ]);

        $organization->members()->attach($user->id, ['role' => OrganizationRole::Owner->value]);

        $this->starterData->handle($organization);

        return $organization;
    }
}
