<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusCategory;
use Database\Factories\WorkflowStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_type_id
 * @property string $name
 * @property StatusCategory $category
 * @property string $color
 * @property int $position
 * @property bool $is_default
 */
#[Fillable(['name', 'category', 'color', 'position', 'is_default'])]
class WorkflowState extends Model
{
    /** @use HasFactory<WorkflowStateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ProjectType, $this>
     */
    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => StatusCategory::class,
            'position' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
