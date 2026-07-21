<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreLabelRequest;
use App\Http\Requests\Settings\UpdateLabelRequest;
use App\Models\Label;
use App\Models\Organization;
use App\Models\Project;
use App\Services\CurrentOrganization;
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
                ->map(fn (Label $label): array => $this->payload($label))
                ->all()
        );
    }

    public function store(StoreLabelRequest $request, CurrentOrganization $current): JsonResponse
    {
        $organization = $current->require($this->currentUser($request));
        $this->authorize('update', $organization);

        $label = $organization->labels()->create($request->validated());

        return response()->json($this->payload($label), 201);
    }

    public function update(UpdateLabelRequest $request, Label $label): JsonResponse
    {
        $this->authorize('update', $label);

        $label->update($request->validated());

        return response()->json($this->payload($label->refresh()));
    }

    public function destroy(Label $label): JsonResponse
    {
        $this->authorize('delete', $label);

        $label->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Label $label): array
    {
        return [
            'id' => $label->id,
            'name' => $label->name,
            'color' => $label->color->value,
        ];
    }
}
