<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Resolves which organization the user is currently looking at. Held in the
 * session so it survives navigation, and always re-checked against membership
 * so a stale id cannot outlive being removed from an org.
 */
class CurrentOrganization
{
    private const KEY = 'current_organization_id';

    public function __construct(private readonly Session $session) {}

    public function for(User $user): ?Organization
    {
        $id = $this->session->get(self::KEY);

        $organization = $id === null
            ? null
            : Organization::query()->visibleTo($user)->whereKey($id)->first();

        // No pick yet, or the pick is no longer theirs: fall back to any org
        // they belong to.
        if ($organization === null) {
            $organization = Organization::query()->visibleTo($user)->orderBy('name')->first();

            if ($organization !== null) {
                $this->set($organization);
            }
        }

        return $organization;
    }

    /**
     * The current organization, required: aborts when the user belongs to none.
     */
    public function require(User $user): Organization
    {
        $organization = $this->for($user);

        abort_if($organization === null, 403);

        return $organization;
    }

    public function set(Organization $organization): void
    {
        $this->session->put(self::KEY, $organization->id);
    }
}
