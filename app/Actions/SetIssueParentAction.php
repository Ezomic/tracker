<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;

class SetIssueParentAction
{
    public function handle(Issue $issue, ?Issue $parent): void
    {
        $issue->forceFill(['parent_id' => $parent?->id])->save();
    }
}
