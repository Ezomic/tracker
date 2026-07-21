<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use App\Support\Cast;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $category_id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string $color
 * @property int|null $archive_after_days
 * @property list<string>|null $github_repos
 * @property string|null $production_url
 * @property int|null $billr_project_id
 * @property int|null $billr_client_id
 * @property int $next_number
 * @property Carbon|null $archived_at
 */
#[Fillable(['key', 'name', 'description', 'color', 'github_repos', 'production_url', 'archive_after_days', 'category_id'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Every time entry logged against any of the project's issues.
     *
     * @return HasManyThrough<TimeEntry, Issue, $this>
     */
    public function timeEntries(): HasManyThrough
    {
        return $this->hasManyThrough(TimeEntry::class, Issue::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('level', 'is_favorite', 'own_issues_only')
            ->withTimestamps();
    }

    /**
     * The user's directly granted level on this project, ignoring anything
     * their organization role implies.
     */
    public function grantFor(User $user): ?ProjectLevel
    {
        $member = $this->members()->find($user->id);

        if ($member === null) {
            return null;
        }

        /** @var Pivot $pivot */
        $pivot = $member->getAttribute('pivot');

        return ProjectLevel::from(Cast::string($pivot->getAttribute('level')));
    }

    /**
     * The effective level: the highest of the direct grant and what the user's
     * organization role implies (owners and admins get admin on every project),
     * or null when they have no access at all.
     */
    public function effectiveLevel(User $user): ?ProjectLevel
    {
        $implied = ($this->organization?->roleFor($user)?->manages() ?? false)
            ? ProjectLevel::Admin
            : null;

        return ProjectLevel::max($this->grantFor($user), $implied);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /**
     * Whether the user's access is limited to issues they report or are assigned.
     * Only a direct restricted grant restricts; an org role that manages
     * (owner/admin) implies full admin and is never restricted.
     */
    public function restrictsToOwnIssues(User $user): bool
    {
        if ($this->organization?->roleFor($user)?->manages() ?? false) {
            return false;
        }

        $member = $this->members()->find($user->id);

        if ($member === null) {
            return false;
        }

        /** @var Pivot $pivot */
        $pivot = $member->getAttribute('pivot');

        return (bool) $pivot->getAttribute('own_issues_only');
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // A direct grant, or being an owner/admin of the project's organization
        // (which implies admin on every project in it).
        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->whereHas('members', fn (Builder $members) => $members->whereKey($user->id))
                ->orWhereHas('organization.members', fn (Builder $members) => $members
                    ->whereKey($user->id)
                    ->whereIn('organization_user.role', [OrganizationRole::Owner->value, OrganizationRole::Admin->value]));
        });
    }

    /**
     * Narrow a listing to the organization being viewed. Membership remains the
     * security boundary; this is which workspace you are looking at.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeInOrganization(Builder $query, ?Organization $organization): Builder
    {
        // No organization in play (a user who predates one, or has none yet):
        // filter nothing rather than matching organization_id = null, which
        // would silently surface unfiled projects.
        return $organization === null
            ? $query
            : $query->where('projects.organization_id', $organization->id);
    }

    public function hasIssues(): bool
    {
        return $this->issues()->exists();
    }

    public function billrLinked(): bool
    {
        return $this->billr_project_id !== null;
    }

    /**
     * The normalised repo base URL (e.g. https://github.com/owner/repo) of the
     * project's first GitHub repo, or null. Used as a fallback for issue links.
     */
    public function primaryRepoBase(): ?string
    {
        $repos = $this->github_repos ?? [];

        return $repos === [] ? null : $this->repoBase($repos[0]);
    }

    /**
     * External reference links for this project.
     *
     * @return array{docs: string|null, production: string|null, repos: list<array{name: string, url: string}>}
     */
    public function links(): array
    {
        $production = $this->production_url !== null && $this->production_url !== ''
            ? rtrim($this->production_url, '/')
            : null;

        return [
            'docs' => $production !== null ? $production.'/docs' : null,
            'production' => $production,
            'repos' => $this->repoLinks(),
        ];
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private function repoLinks(): array
    {
        return array_values(array_map(
            fn (string $repo): array => [
                'name' => $this->repoSlug($repo),
                'url' => $this->repoBase($repo),
            ],
            array_filter(
                $this->github_repos ?? [],
                fn (string $repo): bool => $repo !== '',
            ),
        ));
    }

    private function repoBase(string $repo): string
    {
        return str_starts_with($repo, 'http')
            ? rtrim($repo, '/')
            : 'https://github.com/'.trim($repo, '/');
    }

    private function repoSlug(string $repo): string
    {
        if (str_starts_with($repo, 'http')) {
            $path = trim((string) parse_url($repo, PHP_URL_PATH), '/');

            return $path !== '' ? $path : $repo;
        }

        return trim($repo, '/');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'github_repos' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
