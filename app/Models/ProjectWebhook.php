<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectWebhookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $url
 * @property string $secret
 * @property bool $active
 * @property Carbon|null $last_delivered_at
 * @property int|null $last_status
 * @property string|null $last_error
 * @property-read Project $project
 */
#[Fillable(['url', 'active'])]
// The secret signs outgoing deliveries; it is shown once at creation and never
// serialized again.
#[Hidden(['secret'])]
class ProjectWebhook extends Model
{
    /** @use HasFactory<ProjectWebhookFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_delivered_at' => 'datetime',
        ];
    }
}
