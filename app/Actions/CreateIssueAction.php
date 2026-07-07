<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateIssueAction
{
    public function handle(Team $team, string $title, IssueType $type, ?string $description = null): Issue
    {
        return DB::transaction(function () use ($team, $title, $type, $description) {
            $number = DB::select(
                'update teams set next_number = next_number + 1 where id = ? returning next_number',
                [$team->id]
            )[0]->next_number;

            $identifier = "{$team->key}-{$number}";
            $slug = (string) Str::of($title)->slug()->limit(50, '');
            $branchName = sprintf(
                '%s/%s-%s',
                $type === IssueType::Fix ? 'fix' : 'feature',
                $identifier,
                $slug,
            );

            $issue = new Issue;
            $issue->forceFill([
                'team_id' => $team->id,
                'number' => $number,
                'identifier' => $identifier,
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'type' => $type,
                'priority' => IssuePriority::None,
                'status' => IssueStatus::Backlog,
                'branch_name' => $branchName,
            ])->save();

            return $issue;
        });
    }
}
