<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LabelColor;
use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property LabelColor $color
 */
#[Fillable(['name', 'color'])]
class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsToMany<Issue, $this>
     */
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class);
    }

    /**
     * Labels usable on a project's issues: its organization's set.
     *
     * @param  Builder<Label>  $query
     * @return Builder<Label>
     */
    public function scopeForProject(Builder $query, Project $project): Builder
    {
        return $query->where('organization_id', $project->organization_id);
    }

    /**
     * @param  Builder<Label>  $query
     * @return Builder<Label>
     */
    public function scopeForOrganization(Builder $query, ?Organization $organization): Builder
    {
        return $query->where('organization_id', $organization?->id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => LabelColor::class,
        ];
    }
}
