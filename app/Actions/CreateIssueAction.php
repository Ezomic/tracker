<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\IssueTemplate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateIssueAction
{
    public function handle(Project $project, string $title, IssueType $type, ?string $description = null, ?Issue $parent = null, ?User $owner = null, ?User $assignee = null, ?IssuePriority $priority = null, ?IssueTemplate $template = null, ?string $source = null, ?string $externalRef = null): Issue
    {
        return DB::transaction(function () use ($project, $title, $type, $description, $parent, $owner, $assignee, $priority, $template, $source, $externalRef) {
            $row = (array) DB::selectOne(
                'update projects set next_number = next_number + 1 where id = ? returning next_number',
                [$project->id]
            );
            $number = is_numeric($row['next_number'] ?? null) ? (int) $row['next_number'] : 0;

            $identifier = "{$project->key}-{$number}";
            $slug = (string) Str::of($title)->slug()->limit(50, '');
            $branchName = sprintf(
                '%s/%s-%s',
                $type === IssueType::Fix ? 'fix' : 'feature',
                $identifier,
                $slug,
            );

            $issue = new Issue;
            $issue->forceFill([
                'project_id' => $project->id,
                'owner_id' => $owner?->id,
                'assignee_id' => $assignee?->id,
                'parent_id' => $parent?->id,
                'template_id' => $template?->id,
                'source' => $source,
                'external_ref' => $externalRef,
                'number' => $number,
                'identifier' => $identifier,
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'type' => $type,
                'priority' => $priority ?? IssuePriority::None,
                'status' => IssueStatus::Backlog,
                'branch_name' => $branchName,
            ])->save();

            return $issue;
        });
    }
}
