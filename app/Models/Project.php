<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $color
 * @property list<string>|null $github_repos
 * @property string|null $production_url
 * @property int $next_number
 */
#[Fillable(['key', 'name', 'color', 'github_repos', 'production_url'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
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
