<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateOrganizationSettingsRequest;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function __construct(private readonly CurrentOrganization $current) {}

    public function edit(Request $request): Response
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('update', $organization);

        return Inertia::render('settings/Organization', [
            'organization' => [
                'name' => $organization->name,
                'aiDraftingEnabled' => $organization->ai_drafting_enabled,
            ],
        ]);
    }

    public function update(UpdateOrganizationSettingsRequest $request): RedirectResponse
    {
        $organization = $this->current->require($this->currentUser($request));
        $this->authorize('update', $organization);

        $organization->update(['ai_drafting_enabled' => $request->boolean('ai_drafting_enabled')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization settings saved.')]);

        return to_route('organization.edit');
    }
}
