<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;

class UpdateNoteAction
{
    public function handle(Note $note, string $title, string $body): Note
    {
        $note->update(['title' => $title, 'body' => $body]);

        return $note;
    }
}
