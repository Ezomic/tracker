<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProjectRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProjectAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, User $owner, ?Organization $organization = null): Project
    {
        return DB::transaction(function () use ($attributes, $owner, $organization): Project {
            $project = Project::create($attributes);
            $project->forceFill(['organization_id' => $organization?->id])->save();

            $project->members()->attach($owner->id, [
                'role' => ProjectRole::Owner->value,
                'is_favorite' => true,
            ]);

            return $project;
        });
    }
}
