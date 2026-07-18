<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CommitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $issue_id
 * @property string $repository
 * @property string $sha
 * @property string $branch
 * @property string $message
 * @property string|null $author_name
 * @property string|null $url
 * @property Carbon $committed_at
 */
#[Fillable(['issue_id', 'repository', 'sha', 'branch', 'message', 'author_name', 'url', 'committed_at'])]
class Commit extends Model
{
    /** @use HasFactory<CommitFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'committed_at' => 'datetime',
        ];
    }
}
