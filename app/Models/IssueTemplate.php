<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Cadence;
use App\Enums\IssuePriority;
use App\Enums\IssueType;
use Carbon\CarbonImmutable;
use Database\Factories\IssueTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property IssueType|null $type
 * @property IssuePriority|null $priority
 * @property Cadence $cadence
 * @property CarbonImmutable|null $next_run_at
 * @property int|null $target_project_id
 */
#[Fillable(['name', 'description', 'type', 'priority', 'cadence', 'next_run_at', 'target_project_id'])]
class IssueTemplate extends Model
{
    /** @use HasFactory<IssueTemplateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Labels applied to issues filed from this template.
     *
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    /**
     * The project recurring issues are filed into.
     *
     * @return BelongsTo<Project, $this>
     */
    public function targetProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'target_project_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IssueType::class,
            'priority' => IssuePriority::class,
            'cadence' => Cadence::class,
            'next_run_at' => 'immutable_datetime',
        ];
    }
}
