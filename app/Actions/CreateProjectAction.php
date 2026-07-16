<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProjectAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, User $owner): Project
    {
        return DB::transaction(function () use ($attributes, $owner): Project {
            $project = Project::create($attributes);

            $project->members()->attach($owner->id, [
                'role' => ProjectRole::Owner->value,
                'is_favorite' => true,
            ]);

            return $project;
        });
    }
}
