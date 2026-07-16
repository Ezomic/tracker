<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('projects/Index', [
            'projects' => Project::query()
                ->withCount([
                    'issues as open_count' => fn (Builder $query) => $query
                        ->whereNull('archived_at')
                        ->where('status', '!=', IssueStatus::Done->value),
                ])
                ->orderByDesc('is_favorite')
                ->orderBy('key')
                ->get()
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'key' => $project->key,
                    'name' => $project->name,
                    'color' => $project->color,
                    'isFavorite' => $project->is_favorite,
                    'openCount' => (int) $project->getAttribute('open_count'),
                    'links' => $project->links(),
                ]),
        ]);
    }

    public function toggleFavorite(Project $project): RedirectResponse
    {
        $project->forceFill(['is_favorite' => ! $project->is_favorite])->save();

        return back();
    }
}
