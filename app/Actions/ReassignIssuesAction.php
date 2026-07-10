<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReassignIssuesAction
{
    /**
     * Move issues to their target project, renumbering per project.
     *
     * New (empty) projects renumber their intake 1..N in original-number order;
     * projects that already hold issues append the intake at next_number onward,
     * leaving their existing issues untouched. Identifier and branch_name are
     * regenerated; everything else (title, description, type, status, dates,
     * parent_id) is preserved. Issues already in their target are skipped, so
     * the command is safe to re-run.
     *
     * @param  array<string, string>  $map  oldIdentifier => targetProjectKey
     * @return array{moved: int, skipped: int, missing: list<string>}
     */
    public function handle(array $map): array
    {
        return DB::transaction(function () use ($map) {
            /** @var array<string, list<string>> $byKey */
            $byKey = [];
            foreach ($map as $identifier => $key) {
                $byKey[strtoupper($key)][] = $identifier;
            }

            $moved = 0;
            $skipped = 0;
            $missing = [];

            foreach ($byKey as $key => $identifiers) {
                $project = Project::query()->where('key', $key)->firstOrFail();

                // Ordered by original number so renumbering follows the original chronology.
                $issues = Issue::query()
                    ->whereIn('identifier', $identifiers)
                    ->orderBy('number')
                    ->get();

                foreach (array_diff($identifiers, $issues->pluck('identifier')->all()) as $notFound) {
                    $missing[] = $notFound;
                }

                $next = $project->next_number;

                foreach ($issues as $issue) {
                    if ($issue->project_id === $project->id) {
                        $skipped++;

                        continue;
                    }

                    $next++;
                    $newIdentifier = "{$key}-{$next}";
                    $slug = (string) Str::of($issue->title)->slug()->limit(50, '');
                    $branch = sprintf(
                        '%s/%s-%s',
                        $issue->type === IssueType::Fix ? 'fix' : 'feature',
                        $newIdentifier,
                        $slug,
                    );

                    $issue->timestamps = false;
                    $issue->forceFill([
                        'project_id' => $project->id,
                        'number' => $next,
                        'identifier' => $newIdentifier,
                        'branch_name' => $branch,
                    ])->save();

                    $moved++;
                }

                $project->forceFill(['next_number' => $next])->save();
            }

            return ['moved' => $moved, 'skipped' => $skipped, 'missing' => $missing];
        });
    }
}
