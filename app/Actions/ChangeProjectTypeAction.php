<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectType;
use Illuminate\Support\Facades\DB;

class ChangeProjectTypeAction
{
    public function __construct(private readonly MoveIssueToStateAction $mover) {}

    /**
     * Point a project at a different type and carry its issues across. Each
     * issue lands on a lane of the new type sharing its current lane's
     * category, or on the new type's default lane when nothing matches.
     * Without the remap the issues would keep referencing the old type's
     * lanes and vanish from a board that now renders the new type's.
     */
    public function handle(Project $project, ?ProjectType $type): void
    {
        DB::transaction(function () use ($project, $type): void {
            $type === null
                ? $this->detachIssues($project)
                : $this->remapIssues($project, $type);

            $project->forceFill(['project_type_id' => $type?->id])->save();
        });
    }

    private function remapIssues(Project $project, ProjectType $type): void
    {
        $lanes = $type->states()->get();
        $default = $lanes->firstWhere('is_default', true) ?? $lanes->first();

        if ($default === null) {
            return;
        }

        $project->issues()->with('workflowState')->get()
            ->each(function (Issue $issue) use ($lanes, $default): void {
                $category = $issue->workflowState?->category;

                $this->mover->handle(
                    $issue,
                    ($category === null ? null : $lanes->firstWhere('category', $category)) ?? $default,
                );
            });
    }

    /**
     * A project with no type falls back to the legacy status columns, so drop
     * the lane references rather than leave them pointing at a type the
     * project no longer uses. The status column already mirrors them.
     */
    private function detachIssues(Project $project): void
    {
        $project->issues()->whereNotNull('workflow_state_id')->update(['workflow_state_id' => null]);
    }
}
