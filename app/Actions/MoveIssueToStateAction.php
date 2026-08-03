<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\WorkflowState;
use Carbon\CarbonImmutable;

class MoveIssueToStateAction
{
    /**
     * Move an issue into a workflow state. Keeps the legacy status column in
     * sync (so API/filters keep working until the enum is retired) and stamps
     * closed_at when the target lane's category is completed.
     */
    public function handle(Issue $issue, WorkflowState $state): void
    {
        $issue->forceFill([
            'workflow_state_id' => $state->id,
            'status' => $this->legacyStatus($state),
            'closed_at' => $state->category === StatusCategory::Completed
                ? ($issue->closed_at ?? CarbonImmutable::now())
                : null,
        ])->save();
    }

    private function legacyStatus(WorkflowState $state): IssueStatus
    {
        return match ($state->category) {
            StatusCategory::Backlog, StatusCategory::Unstarted => IssueStatus::Backlog,
            StatusCategory::Completed, StatusCategory::Canceled => IssueStatus::Done,
            StatusCategory::Started => $this->startedStatus($state),
        };
    }

    /**
     * The first started lane maps to In Progress and any later one to In Review,
     * so the default type round-trips exactly and custom types stay sensible.
     */
    private function startedStatus(WorkflowState $state): IssueStatus
    {
        $startedIds = WorkflowState::query()
            ->where('project_type_id', $state->project_type_id)
            ->where('category', StatusCategory::Started->value)
            ->orderBy('position')
            ->pluck('id')
            ->values();

        return $startedIds->search($state->id) === 0
            ? IssueStatus::InProgress
            : IssueStatus::InReview;
    }
}
