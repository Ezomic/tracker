<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueRelation;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LinkIssuesAction
{
    /**
     * Link two issues, writing both directions in one transaction so a query
     * from either side is a plain lookup and the pair cannot half-exist.
     *
     * Idempotent: relinking the same pair leaves one row per direction.
     */
    public function handle(Issue $issue, Issue $related, IssueRelation $relation, ?User $actor = null): void
    {
        DB::transaction(function () use ($issue, $related, $relation, $actor): void {
            $this->write($issue, $related, $relation, $actor);
            $this->write($related, $issue, $relation->inverse(), $actor);

            $issue->recordActivity('linked', [
                'relation' => $relation->value,
                'issue' => $related->identifier,
            ]);
        });
    }

    /**
     * Remove both directions. Takes the relation as stored on `$issue`.
     */
    public function unlink(Issue $issue, Issue $related, IssueRelation $relation): void
    {
        DB::transaction(function () use ($issue, $related, $relation): void {
            IssueLink::query()
                ->where('issue_id', $issue->id)
                ->where('related_issue_id', $related->id)
                ->where('relation', $relation->value)
                ->delete();

            IssueLink::query()
                ->where('issue_id', $related->id)
                ->where('related_issue_id', $issue->id)
                ->where('relation', $relation->inverse()->value)
                ->delete();

            $issue->recordActivity('unlinked', [
                'relation' => $relation->value,
                'issue' => $related->identifier,
            ]);
        });
    }

    private function write(Issue $from, Issue $to, IssueRelation $relation, ?User $actor): void
    {
        // issue_id is deliberately not fillable: a link's ends are set here,
        // never taken from a request. Hence the explicit lookup and forceFill
        // rather than firstOrNew, which mass-assigns its search attributes.
        $link = IssueLink::query()
            ->where('issue_id', $from->id)
            ->where('related_issue_id', $to->id)
            ->where('relation', $relation->value)
            ->first() ?? new IssueLink;

        $link->forceFill([
            'issue_id' => $from->id,
            'related_issue_id' => $to->id,
            'relation' => $relation->value,
            'created_by' => $actor?->id,
        ])->save();
    }
}
