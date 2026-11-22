<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ProjectVisit;
use App\Models\User;

class RecordProjectVisitAction
{
    public function handle(User $user, int $projectId): void
    {
        if ($user->is_service) {
            return;
        }

        ProjectVisit::query()->upsert(
            [['user_id' => $user->id, 'project_id' => $projectId, 'visited_at' => now()]],
            ['user_id', 'project_id'],
            ['visited_at'],
        );
    }
}
