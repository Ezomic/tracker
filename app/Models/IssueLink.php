<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IssueRelation;
use Database\Factories\IssueLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $issue_id
 * @property int $related_issue_id
 * @property IssueRelation $relation
 * @property int|null $created_by
 * @property-read Issue $issue
 * @property-read Issue $relatedIssue
 */
#[Fillable(['related_issue_id', 'relation', 'created_by'])]
class IssueLink extends Model
{
    /** @use HasFactory<IssueLinkFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function relatedIssue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'related_issue_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation' => IssueRelation::class,
        ];
    }
}
