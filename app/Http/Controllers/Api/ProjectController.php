<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Project::query()->visibleTo($request->user())->orderBy('key')->get(['key', 'name', 'color'])
        );
    }
}
