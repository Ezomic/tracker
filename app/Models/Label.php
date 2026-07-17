<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LabelColor;
use App\Enums\ProjectRole;
use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property LabelColor $color
 */
#[Fillable(['name', 'color'])]
class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory;

    /**
     * The label's owner. Becomes the organization once that entity exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Issue, $this>
     */
    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class);
    }

    /**
     * Labels usable on a project's issues: the project owner's set.
     *
     * @param  Builder<Label>  $query
     * @return Builder<Label>
     */
    public function scopeForProject(Builder $query, Project $project): Builder
    {
        return $query->where('user_id', $project->ownerId());
    }

    /**
     * Labels usable across every project the user can see — the owners' sets.
     *
     * @param  Builder<Label>  $query
     * @return Builder<Label>
     */
    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('user_id', DB::table('project_user')
            ->whereIn('project_id', Project::query()->visibleTo($user)->select('projects.id'))
            ->where('role', ProjectRole::Owner->value)
            ->select('user_id'));
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
