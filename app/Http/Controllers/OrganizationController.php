<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;

class OrganizationController extends Controller
{
    public function switch(Organization $organization, CurrentOrganization $current): RedirectResponse
    {
        $this->authorize('view', $organization);

        $current->set($organization);

        // Land somewhere that always exists in the new org.
        return to_route('dashboard');
    }
}
