<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Project;
use App\Models\User;

class CreateNoteAction
{
    public function handle(Project $project, User $author, string $title, string $body): Note
    {
        return $project->notes()->create([
            'user_id' => $author->id,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
