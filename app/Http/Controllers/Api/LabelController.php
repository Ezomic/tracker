<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project' => ['sometimes', 'string', 'exists:projects,key'],
        ]);

        $organization = isset($validated['project'])
            ? Project::query()->where('key', $validated['project'])->firstOrFail()->organization
            : Organization::query()->visibleTo($this->currentUser($request))->orderBy('name')->first();

        abort_if($organization === null, 404);

        $this->authorize('viewLibrary', $organization);

        return response()->json(
            Label::query()
                ->forOrganization($organization)
                ->orderBy('name')
                ->get()
                ->map(fn (Label $label): array => [
                    'name' => $label->name,
                    'color' => $label->color->value,
                ])
                ->all()
        );
    }
}
