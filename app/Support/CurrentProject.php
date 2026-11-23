<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Issue;
use App\Models\Project;
use Illuminate\Http\Request;

final class CurrentProject
{
    /**
     * The project the user is currently "inside": a project-scoped route, or
     * the project of the issue being viewed.
     */
    public static function id(Request $request): ?int
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        $project = $route->parameter('project');

        if ($project instanceof Project) {
            return $project->id;
        }

        $issue = $route->parameter('issue');

        return $issue instanceof Issue ? $issue->project_id : null;
    }
}
