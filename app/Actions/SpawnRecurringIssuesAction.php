<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Cadence;
use App\Enums\IssueType;
use App\Models\IssueTemplate;
use Carbon\CarbonImmutable;

class SpawnRecurringIssuesAction
{
    public function __construct(private readonly CreateIssueAction $createIssue) {}

    /**
     * File an issue from every template whose next run is due, then advance each
     * template to its next future occurrence. A template that missed several
     * periods still spawns only one issue per run, so a paused scheduler cannot
     * flood a project on catch-up.
     */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        $templates = IssueTemplate::query()
            ->with(['labels', 'targetProject'])
            ->where('cadence', '!=', Cadence::None->value)
            ->whereNotNull('target_project_id')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->get();

        $count = 0;

        foreach ($templates as $template) {
            $project = $template->targetProject;

            if ($project === null) {
                continue;
            }

            $issue = $this->createIssue->handle(
                $project,
                $template->name,
                $template->type ?? IssueType::Feature,
                $template->description,
                priority: $template->priority,
            );

            $issue->labels()->sync($template->labels->pluck('id')->all());

            $next = $template->next_run_at;

            do {
                $next = $template->cadence->advance($next);
            } while ($next->lessThanOrEqualTo($now));

            $template->forceFill(['next_run_at' => $next])->save();

            $count++;
        }

        return $count;
    }
}
