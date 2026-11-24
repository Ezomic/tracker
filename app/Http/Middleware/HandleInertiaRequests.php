<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\IssueStatus;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectVisit;
use App\Models\User;
use App\Services\CurrentOrganization;
use App\Services\Integrations;
use App\Services\Portal\IdPortalClient;
use App\Support\Cast;
use App\Support\CurrentProject;
use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
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

    private const int RECENT_LIMIT = 5;

    /** A project counts as active when an issue in it changed this recently. */
    private const int ACTIVE_DAYS = 7;

    public function __construct(private readonly Integrations $integrations) {}

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
            'integrations' => [
                'sso' => $this->integrations->sso(),
                'notion' => $this->integrations->notion(),
            ],
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
                'aiDrafting' => $this->integrations->drafting($organization),
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
            'sidebarCategories' => fn () => $this->sidebarCategories($request->user(), $organization, CurrentProject::id($request)),
            // Every project the user can file against, for the sidebar's new-issue
            // modal (which can be opened from any page).
            'newIssueProjects' => fn () => $request->user() !== null
                ? $request->user()->projects()
                    ->notArchived()
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
            'currentProjectId' => fn () => CurrentProject::id($request),
            'notifications' => fn () => $request->user() !== null
                ? $request->user()->notifications()->latest()->limit(15)->get()->map(fn ($notification) => [
                    'id' => $notification->id,
                    'data' => $notification->getAttribute('data'),
                    'read' => $notification->getAttribute('read_at') !== null,
                    'createdAt' => Date::make($notification->getAttribute('created_at'))?->toIso8601String(),
                ])
                : [],
            'unreadNotificationsCount' => fn () => $request->user() !== null
                ? $request->user()->unreadNotifications()->count()
                : 0,
            'portalApps' => fn () => $request->user() === null
                ? []
                : app(IdPortalClient::class)->appsFor($request->user())['apps'],
            'portalCategories' => fn () => $request->user() === null
                ? []
                : app(IdPortalClient::class)->appsFor($request->user())['categories'],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The organization's category tree for the sidebar: each category (in
     * depth-first order, with `depth` for indentation) carries the user's
     * launchable projects assigned directly to it, plus a separate bucket for
     * projects with no category. `recent` repeats the last visited projects;
     * the frontend decides what to show from `active` and `recent`.
     *
     * @return array{recent: array<int, array<string, mixed>>, tree: array<int, array{id: int, name: string, parentId: int|null, depth: int, projects: array<int, array<string, mixed>>}>, uncategorized: array<int, array<string, mixed>>}
     */
    private function sidebarCategories(?User $user, ?Organization $organization, ?int $currentProjectId): array
    {
        if ($user === null) {
            return ['recent' => [], 'tree' => [], 'uncategorized' => []];
        }

        // Epics live in the board's epic panel, not its columns, so they are
        // left out here too; their sub-issues are still counted.
        $countable = fn (IssueStatus $status): Closure => fn (Builder $query) => $query
            ->whereNull('archived_at')
            ->where('status', $status->value)
            ->whereDoesntHave('children');

        $projects = $user->projects()
            ->notArchived()
            ->inOrganization($organization)
            ->select(['projects.id', 'key', 'name', 'color', 'category_id'])
            ->withCount([
                'issues as backlog_count' => $countable(IssueStatus::Backlog),
                'issues as in_progress_count' => $countable(IssueStatus::InProgress),
                'issues as in_review_count' => $countable(IssueStatus::InReview),
                'issues as done_count' => $countable(IssueStatus::Done),
            ])
            // Archived issues are off the board, so touching one is not activity.
            ->withExists(['issues as active' => fn (Builder $query) => $query
                ->whereNull('archived_at')
                ->where('updated_at', '>=', now()->subDays(self::ACTIVE_DAYS))])
            ->orderBy('key')
            ->get();

        $map = fn (Project $project): array => [
            'id' => $project->id,
            'key' => $project->key,
            'name' => $project->name,
            'color' => $project->color,
            'active' => (bool) $project->getAttribute('active'),
            'counts' => [
                'backlog' => Cast::int($project->getAttribute('backlog_count')),
                'in_progress' => Cast::int($project->getAttribute('in_progress_count')),
                'in_review' => Cast::int($project->getAttribute('in_review_count')),
                'done' => Cast::int($project->getAttribute('done_count')),
            ],
        ];

        $byCategory = $projects->groupBy('category_id');

        $tree = Category::orderedTree($organization)
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'parentId' => $category->parent_id,
                'depth' => Cast::int($category->getAttribute('depth')),
                'projects' => $byCategory->get($category->id, new Collection)->map($map)->values()->all(),
            ])
            ->values()
            ->all();

        $uncategorized = $projects->whereNull('category_id')->map($map)->values()->all();

        return [
            'recent' => $this->recentProjects($user, $projects, $currentProjectId)->map($map)->values()->all(),
            'tree' => $tree,
            'uncategorized' => $uncategorized,
        ];
    }

    /**
     * The last projects the user visited, most recent first, limited to the
     * sidebar's own (already membership- and organization-scoped) projects.
     * The project being viewed counts as the latest visit: its visit is only
     * recorded once this response has been built.
     *
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Project>
     */
    private function recentProjects(User $user, Collection $projects, ?int $currentProjectId): Collection
    {
        $visible = $projects->keyBy('id');

        $visited = $user->projectVisits()
            ->whereIn('project_id', $visible->keys())
            ->orderByDesc('visited_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->toBase()
            ->map(fn (ProjectVisit $visit): int => $visit->project_id);

        if ($currentProjectId !== null) {
            $visited->prepend($currentProjectId);
        }

        return $visited->unique()
            ->map(fn (int $id): ?Project => $visible->get($id))
            ->filter()
            ->take(self::RECENT_LIMIT)
            ->values();
    }
}
