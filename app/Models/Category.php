<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $parent_id
 * @property string $name
 */
#[Fillable(['name', 'parent_id'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeForOrganization(Builder $query, ?Organization $organization): Builder
    {
        return $query->where('organization_id', $organization?->id);
    }

    /**
     * The organization's categories in depth-first (pre-order) order, each with
     * a transient `depth` attribute set for indentation. Siblings are ordered by
     * name. Pass $withProjectsCount to also load `projects_count`.
     *
     * @return Collection<int, self>
     */
    public static function orderedTree(?Organization $organization, bool $withProjectsCount = false): Collection
    {
        if ($organization === null) {
            return new Collection;
        }

        $query = self::query()->where('organization_id', $organization->id)->orderBy('name');

        if ($withProjectsCount) {
            $query->withCount('projects');
        }

        $byParent = $query->get()->groupBy('parent_id');

        /** @var Collection<int, self> $ordered */
        $ordered = new Collection;

        $walk = function (?int $parentId, int $depth) use (&$walk, $ordered, $byParent): void {
            foreach ($byParent->get($parentId, new Collection) as $category) {
                $category->setAttribute('depth', $depth);
                $ordered->push($category);
                $walk($category->id, $depth + 1);
            }
        };

        $walk(null, 0);

        return $ordered;
    }

    /**
     * The ids of every category beneath this one, at any depth. Used to reject a
     * reparent that would create a cycle. Computed over the organization's tree.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $byParent = self::query()
            ->where('organization_id', $this->organization_id)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $collect = function (int $parentId) use (&$collect, $byParent): array {
            $ids = [];

            foreach ($byParent->get($parentId, collect()) as $child) {
                $ids[] = $child->id;
                $ids = array_merge($ids, $collect($child->id));
            }

            return $ids;
        };

        return array_values($collect($this->id));
    }
}
