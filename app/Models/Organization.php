<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Support\Cast;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
#[Fillable(['name', 'slug'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<IssueTemplate, $this>
     */
    public function issueTemplates(): HasMany
    {
        return $this->hasMany(IssueTemplate::class);
    }

    /**
     * @return HasMany<Label, $this>
     */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleFor(User $user): ?OrganizationRole
    {
        $member = $this->members()->find($user->id);

        if ($member === null) {
            return null;
        }

        /** @var Pivot $pivot */
        $pivot = $member->getAttribute('pivot');

        return OrganizationRole::from(Cast::string($pivot->getAttribute('role')));
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /**
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('members', fn (Builder $members) => $members->whereKey($user->id));
    }
}
