<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Label;
use App\Models\User;
use App\Support\Cast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BulkUpdateIssuesAction
{
    /**
     * Apply one change to many issues.
     *
     * Authorized per issue rather than once for the batch: a selection can span
     * projects, and an issue the actor cannot write is skipped and reported
     * rather than silently dropped or aborting the rest.
     *
     * @param  list<string>  $identifiers
     * @param  array<string, mixed>  $changes
     * @return array{updated: list<string>, skipped: list<array{issue: string, reason: string}>}
     */
    public function handle(array $identifiers, array $changes, User $actor): array
    {
        $updated = [];
        $skipped = [];
        $projects = [];
        $notify = app(NotifyIssueWebhooksAction::class);
        NotifyIssueWebhooksAction::reset();

        DB::transaction(function () use ($identifiers, $changes, $actor, &$updated, &$skipped, &$projects): void {
            foreach (array_unique($identifiers) as $identifier) {
                $issue = Issue::query()->where('identifier', $identifier)->first();

                if ($issue === null) {
                    $skipped[] = ['issue' => $identifier, 'reason' => 'not found'];

                    continue;
                }

                if (! Gate::forUser($actor)->allows('update', $issue)) {
                    $skipped[] = ['issue' => $identifier, 'reason' => 'no write access'];

                    continue;
                }

                $this->apply($issue, $changes, $actor);
                $updated[] = $identifier;
                $projects[$issue->project_id] = $issue->project;
            }
        });

        // One summary per project touched, so an endpoint that hit the cap
        // learns that more happened than it was told about.
        foreach ($projects as $project) {
            $notify->flushSuppressed($project);
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function apply(Issue $issue, array $changes, User $actor): void
    {
        $attributes = [];

        if (array_key_exists('status', $changes)) {
            $status = IssueStatus::from(Cast::string($changes['status']));
            $attributes['status'] = $status;
            $attributes['closed_at'] = $status === IssueStatus::Done ? ($issue->closed_at ?? now()) : null;
        }

        if (array_key_exists('priority', $changes)) {
            $attributes['priority'] = IssuePriority::from(Cast::string($changes['priority']));
        }

        if (array_key_exists('assignee_id', $changes)) {
            $attributes['assignee_id'] = $changes['assignee_id'] === null ? null : Cast::int($changes['assignee_id']);
        }

        if (array_key_exists('archived', $changes)) {
            $attributes['archived_at'] = $changes['archived'] === true ? ($issue->archived_at ?? now()) : null;
        }

        if ($attributes !== []) {
            // forceFill rather than update(): the observer still fires, so each
            // issue keeps its own timeline entry. A bulk change is not a reason
            // to lose the history.
            $issue->forceFill($attributes)->save();
        }

        $this->applyLabels($issue, $changes);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function applyLabels(Issue $issue, array $changes): void
    {
        $add = $this->labelIds($issue, $changes['add_labels'] ?? []);
        $remove = $this->labelIds($issue, $changes['remove_labels'] ?? []);

        if ($add === [] && $remove === []) {
            return;
        }

        $current = Cast::ints($issue->labels()->pluck('labels.id')->all());
        $next = array_values(array_diff(array_unique([...$current, ...$add]), $remove));

        $issue->syncLabelsWithActivity($next);
    }

    /**
     * @return list<int>
     */
    private function labelIds(Issue $issue, mixed $names): array
    {
        if (! is_array($names) || $names === []) {
            return [];
        }

        $wanted = array_map(
            fn (mixed $name): string => mb_strtolower(Cast::string($name)),
            array_values($names),
        );

        return Cast::ints(Label::query()
            ->forProject($issue->project)
            ->get(['id', 'name'])
            ->filter(fn (Label $label): bool => in_array(mb_strtolower($label->name), $wanted, true))
            ->pluck('id')
            ->all());
    }
}
