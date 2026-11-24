<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $user_id
 * @property string $title
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read User|null $author
 */
#[Fillable(['project_id', 'user_id', 'title', 'body'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Notes of projects the user can see, in the organization being viewed.
     *
     * @param  Builder<Note>  $query
     * @return Builder<Note>
     */
    public function scopeVisibleTo(Builder $query, User $user, ?Organization $organization): Builder
    {
        return $query->whereHas('project', fn (Builder $project) => $project
            ->visibleTo($user)
            ->inOrganization($organization));
    }

    /**
     * Notes whose title or body contains the term. Wildcard characters in the
     * term match themselves, and SQLite's LIKE ignores case for ASCII only.
     *
     * @param  Builder<Note>  $query
     * @return Builder<Note>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(fn (Builder $group) => $group
            ->whereRaw("title like ? escape '!'", [$like])
            ->orWhereRaw("body like ? escape '!'", [$like]));
    }
}
