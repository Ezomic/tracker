<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Enums\StatusCategory;
use App\Models\Project;
use App\Models\WorkflowState;
use Illuminate\Support\Collection;

/**
 * The inverse of MoveIssueToStateAction::legacyStatus(): given one of the four
 * legacy statuses, pick the lane in a project's type that means the same thing.
 *
 * This is what lets `status` and `workflow_state` both stay writable while the
 * deprecation window runs. Without it, setting `status` over the API left
 * workflow_state_id pointing at the lane the issue used to be in, so the board
 * and the API disagreed about where the issue was.
 */
class ResolveWorkflowStateAction
{
    public function handle(Project $project, IssueStatus $status): ?WorkflowState
    {
        if ($project->project_type_id === null) {
            return null;
        }

        $states = WorkflowState::query()
            ->where('project_type_id', $project->project_type_id)
            ->orderBy('position')
            ->get();

        if ($states->isEmpty()) {
            return null;
        }

        return match ($status) {
            IssueStatus::Backlog => $this->firstOf($states, StatusCategory::Backlog, StatusCategory::Unstarted),
            IssueStatus::InProgress => $this->started($states, 0),
            IssueStatus::InReview => $this->started($states, 1),
            IssueStatus::Done => $this->firstOf($states, StatusCategory::Completed),
        };
    }

    /**
     * @param  Collection<int, WorkflowState>  $states
     */
    private function firstOf(Collection $states, StatusCategory ...$categories): ?WorkflowState
    {
        foreach ($categories as $category) {
            $match = $states->firstWhere('category', $category);

            if ($match instanceof WorkflowState) {
                return $match;
            }
        }

        return null;
    }

    /**
     * The nth started lane, mirroring how legacyStatus() reads them back: the
     * first started lane is In Progress and any later one is In Review. A type
     * with only one started lane collapses both onto it rather than failing.
     *
     * @param  Collection<int, WorkflowState>  $states
     */
    private function started(Collection $states, int $index): ?WorkflowState
    {
        $started = $states->where('category', StatusCategory::Started)->values();

        $match = $started->get($index) ?? $started->last();

        return $match instanceof WorkflowState ? $match : null;
    }
}
