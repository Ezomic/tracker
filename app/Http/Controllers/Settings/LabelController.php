<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreLabelRequest;
use App\Http\Requests\Settings\UpdateLabelRequest;
use App\Models\Label;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabelController extends Controller
{
    public function __construct(private readonly CurrentOrganization $current) {}

    public function index(Request $request): Response
    {
        $organization = $this->current->for($request->user());
        $this->authorize('view', $organization);

        return Inertia::render('settings/Labels', [
            'labels' => Label::query()
                ->forOrganization($organization)
                ->withCount('issues')
                ->orderBy('name')
                ->get()
                ->map(fn (Label $label) => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color->value,
                    'issuesCount' => $label->issues_count,
                ]),
            'canManage' => $request->user()->can('update', $organization),
        ]);
    }

    public function store(StoreLabelRequest $request): RedirectResponse
    {
        $organization = $this->current->for($request->user());
        $this->authorize('update', $organization);

        $organization->labels()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Label created.')]);

        return to_route('labels.index');
    }

    public function update(UpdateLabelRequest $request, Label $label): RedirectResponse
    {
        $this->authorize('update', $label);

        $label->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Label updated.')]);

        return to_route('labels.index');
    }

    public function destroy(Label $label): RedirectResponse
    {
        $this->authorize('delete', $label);

        $label->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Label deleted.')]);

        return to_route('labels.index');
    }
}
