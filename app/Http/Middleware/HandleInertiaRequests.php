<?php

namespace App\Http\Middleware;

use App\Enums\IssueStatus;
use App\Models\Project;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'projects' => fn () => $request->user()
                ? Project::query()
                    ->select(['id', 'key', 'name', 'color'])
                    ->withCount([
                        'issues as total_count' => fn (Builder $query) => $query->whereNull('archived_at'),
                        'issues as open_count' => fn (Builder $query) => $query
                            ->whereNull('archived_at')
                            ->where('status', '!=', IssueStatus::Done->value),
                    ])
                    ->orderBy('key')
                    ->get()
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'key' => $project->key,
                        'name' => $project->name,
                        'color' => $project->color,
                        'openCount' => (int) $project->getAttribute('open_count'),
                        'totalCount' => (int) $project->getAttribute('total_count'),
                    ])
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
