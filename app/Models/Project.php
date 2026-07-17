<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string $color
 * @property int|null $archive_after_days
 * @property list<string>|null $github_repos
 * @property string|null $production_url
 * @property int $next_number
 */
#[Fillable(['key', 'name', 'description', 'color', 'github_repos', 'production_url', 'archive_after_days'])]
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
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * @return HasMany<IssueTemplate, $this>
     */
    public function issueTemplates(): HasMany
    {
        return $this->hasMany(IssueTemplate::class);
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
            ->withPivot('role', 'is_favorite')
            ->withTimestamps();
    }

    public function owner(): ?User
    {
        return $this->members()->wherePivot('role', ProjectRole::Owner->value)->first();
    }

    /**
     * The owning member's id, without hydrating the user.
     */
    public function ownerId(): ?int
    {
        $id = $this->members()->wherePivot('role', ProjectRole::Owner->value)->value('users.id');

        return $id === null ? null : (int) $id;
    }

    public function roleFor(User $user): ?ProjectRole
    {
        $member = $this->members()->find($user->id);

        if ($member === null) {
            return null;
        }

        /** @var Pivot $pivot */
        $pivot = $member->getAttribute('pivot');

        return ProjectRole::from((string) $pivot->getAttribute('role'));
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('members', fn (Builder $members) => $members->whereKey($user->id));
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
        ];
    }
}
