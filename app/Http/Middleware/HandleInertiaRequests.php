<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Services\CurrentOrganization;
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
        $organization = $request->user() === null
            ? null
            : app(CurrentOrganization::class)->for($request->user());

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => $request->user() === null ? app()->getLocale() : $request->user()->locale,
            'auth' => [
                'user' => $request->user(),
            ],
            'currentOrganization' => $organization === null ? null : [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                // A non-null organization only resolves for an authenticated user.
                'canManage' => $organization->roleFor($request->user())?->manages() ?? false,
                'canViewLibrary' => $request->user()->can('viewLibrary', $organization),
            ],
            'organizations' => fn () => $request->user() === null ? [] : $request->user()
                ->organizations()
                ->orderBy('name')
                ->get()
                ->map(fn (Organization $each) => [
                    'id' => $each->id,
                    'name' => $each->name,
                    'slug' => $each->slug,
                ]),
            'sidebarProjects' => fn () => $request->user()
                ? $request->user()->projects()
                    ->inOrganization($organization)
                    ->wherePivot('is_favorite', true)
                    ->select(['projects.id', 'key', 'name', 'color'])
                    ->withCount([
                        'issues as backlog_count' => fn (Builder $query) => $query->whereNull('archived_at')->where('status', IssueStatus::Backlog->value),
                        'issues as in_progress_count' => fn (Builder $query) => $query->whereNull('archived_at')->where('status', IssueStatus::InProgress->value),
                        'issues as in_review_count' => fn (Builder $query) => $query->whereNull('archived_at')->where('status', IssueStatus::InReview->value),
                        'issues as done_count' => fn (Builder $query) => $query->whereNull('archived_at')->where('status', IssueStatus::Done->value),
                    ])
                    ->orderBy('key')
                    ->get()
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'key' => $project->key,
                        'name' => $project->name,
                        'color' => $project->color,
                        'counts' => [
                            'backlog' => (int) $project->getAttribute('backlog_count'),
                            'in_progress' => (int) $project->getAttribute('in_progress_count'),
                            'in_review' => (int) $project->getAttribute('in_review_count'),
                            'done' => (int) $project->getAttribute('done_count'),
                        ],
                    ])
                : [],
            // Every project the user can file against, for the sidebar's new-issue
            // modal (which can be opened from any page).
            'newIssueProjects' => fn () => $request->user()
                ? $request->user()->projects()
                    ->inOrganization($organization)
                    ->select(['projects.id', 'key', 'name'])
                    ->orderBy('key')
                    ->get()
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'key' => $project->key,
                        'name' => $project->name,
                    ])
                : [],
            'currentProjectId' => fn () => $this->currentProjectId($request),
            'notifications' => fn () => $request->user()
                ? $request->user()->notifications()->latest()->limit(15)->get()->map(fn ($notification) => [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'read' => $notification->read_at !== null,
                    'createdAt' => $notification->created_at?->toIso8601String(),
                ])
                : [],
            'unreadNotificationsCount' => fn () => $request->user()
                ? $request->user()->unreadNotifications()->count()
                : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The project the user is currently "inside", so the new-issue modal can
     * preselect it. Covers project-scoped routes and issue detail pages.
     */
    private function currentProjectId(Request $request): ?int
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
