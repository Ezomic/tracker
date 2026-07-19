<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IssueTemplate;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project' => ['sometimes', 'string', 'exists:projects,key'],
        ]);

        $organization = isset($validated['project'])
            ? Project::query()->where('key', $validated['project'])->firstOrFail()->organization
            : Organization::query()->visibleTo($request->user())->orderBy('name')->first();

        abort_if($organization === null, 404);

        $this->authorize('viewLibrary', $organization);

        $templates = $organization->issueTemplates()->with('labels')->orderBy('name')->get();

        return response()->json($templates->map(fn (IssueTemplate $template): array => [
            'name' => $template->name,
            'description' => $template->description,
            'type' => $template->type?->value,
            'priority' => $template->priority?->value,
            'labels' => $template->labels->pluck('name')->all(),
        ])->all());
    }
}
