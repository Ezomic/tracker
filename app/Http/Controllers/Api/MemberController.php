<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project' => ['required', 'string', 'exists:projects,key'],
        ]);

        $project = Project::query()->where('key', $validated['project'])->firstOrFail();

        $this->authorize('view', $project);

        return response()->json(
            $project->members()
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $user): array => [
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all()
        );
    }
}
