<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\OrganizationRole;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $owner_id
 * @property int|null $assignee_id
 * @property int|null $parent_id
 * @property int $number
 * @property string $identifier
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property int|null $estimate_minutes
 * @property IssueType $type
 * @property IssuePriority $priority
 * @property IssueStatus $status
 * @property string $branch_name
 * @property string|null $github_pr_url
 * @property Carbon|null $closed_at
 * @property Carbon|null $archived_at
 * @property string|null $archive_reason
 */
// owner_id is deliberately not fillable: it is stamped once, at creation.
#[Fillable(['title', 'description', 'estimate_minutes', 'type', 'priority', 'parent_id', 'assignee_id'])]
class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The reporter — stamped when the issue is filed.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'parent_id');
    }

    /**
     * @return HasMany<Issue, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Issue::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /**
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Limit to issues in projects the given user is a member of.
     *
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Grouped so the OR does not leak into sibling conditions on the query.
        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->whereHas('project.members', fn (Builder $members) => $members->whereKey($user->id))
                ->orWhereHas('project.organization.members', fn (Builder $members) => $members
                    ->whereKey($user->id)
                    ->whereIn('organization_user.role', [OrganizationRole::Owner->value, OrganizationRole::Admin->value]));
        });
    }

    /**
     * Narrow a listing to the organization being viewed.
     *
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    public function scopeInOrganization(Builder $query, ?Organization $organization): Builder
    {
        return $organization === null
            ? $query
            : $query->whereHas('project', fn (Builder $project) => $project->where('organization_id', $organization->id));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IssueType::class,
            'priority' => IssuePriority::class,
            'status' => IssueStatus::class,
            'estimate_minutes' => 'integer',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
