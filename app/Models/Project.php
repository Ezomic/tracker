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
 * @property string|null $github_repo
 * @property string|null $production_url
 * @property int $next_number
 */
#[Fillable(['key', 'name', 'color', 'github_repo', 'production_url'])]
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
     * External reference links for this project, each null when unavailable.
     *
     * @return array{docs: string|null, readme: string|null, production: string|null}
     */
    public function links(): array
    {
        $production = $this->production_url !== null && $this->production_url !== ''
            ? rtrim($this->production_url, '/')
            : null;

        return [
            'docs' => $production !== null ? $production.'/docs' : null,
            'readme' => $this->readmeUrl(),
            'production' => $production,
        ];
    }

    private function readmeUrl(): ?string
    {
        if ($this->github_repo === null || $this->github_repo === '') {
            return null;
        }

        $base = str_starts_with($this->github_repo, 'http')
            ? rtrim($this->github_repo, '/')
            : 'https://github.com/'.trim($this->github_repo, '/');

        return $base.'#readme';
    }
}
