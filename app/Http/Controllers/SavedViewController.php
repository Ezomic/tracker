<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedViewRequest;
use App\Models\SavedView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    public function store(StoreSavedViewRequest $request): RedirectResponse
    {
        $criteria = array_filter(
            $request->validated('criteria', []),
            fn ($value) => $value !== null && $value !== '',
        );

        SavedView::query()->create([
            'user_id' => $this->currentUser($request)->id,
            'project_id' => $request->validated('project_id'),
            'name' => $request->validated('name'),
            'criteria' => $criteria,
        ]);

        return back();
    }

    public function destroy(Request $request, SavedView $savedView): RedirectResponse
    {
        abort_unless($savedView->user_id === $this->currentUser($request)->id, 403);

        $savedView->delete();

        return back();
    }
}
