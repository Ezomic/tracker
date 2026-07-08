<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreLabelRequest;
use App\Http\Requests\Settings\UpdateLabelRequest;
use App\Models\Label;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LabelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/Labels', [
            'labels' => Label::query()
                ->withCount('issues')
                ->orderBy('name')
                ->get()
                ->map(fn (Label $label) => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color->value,
                    'issuesCount' => $label->issues_count,
                ]),
        ]);
    }

    public function store(StoreLabelRequest $request): RedirectResponse
    {
        Label::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Label created.')]);

        return to_route('labels.index');
    }

    public function update(UpdateLabelRequest $request, Label $label): RedirectResponse
    {
        $label->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Label updated.')]);

        return to_route('labels.index');
    }

    public function destroy(Label $label): RedirectResponse
    {
        $label->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Label deleted.')]);

        return to_route('labels.index');
    }
}
