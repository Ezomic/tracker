<?php

use App\Enums\IssueStatus;
use App\Enums\StatusCategory;
use App\Support\Cast;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repairs issues whose workflow_state_id is missing or disagrees with status.
 *
 * TRACK-216 stopped new drift, but not the rows that had already drifted: 316
 * issues carried no lane, and 38 were `done` while sitting in a Backlog lane,
 * from back when the API's updateStatus wrote status alone and left the lane
 * pointing where the issue used to be.
 *
 * status is authoritative here. It is the field that was being written when the
 * drift happened, and it is what the dashboard and every consumer have read
 * since. Only workflow_state_id is touched; status and closed_at are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded: a database with no lanes, or nothing to repair, no-ops. That
        // keeps fresh installs and the test suite out of this entirely.
        if (! DB::table('workflow_states')->exists()) {
            return;
        }

        $lanesByType = $this->lanesByType();

        if ($lanesByType === []) {
            return;
        }

        /** @var array<int, string> $categories */
        $categories = DB::table('workflow_states')->pluck('category', 'id')
            ->mapWithKeys(fn (mixed $category, mixed $id): array => [Cast::int($id) => Cast::string($category)])
            ->all();

        DB::table('issues')
            ->join('projects', 'projects.id', '=', 'issues.project_id')
            ->whereNotNull('projects.project_type_id')
            ->select([
                'issues.id',
                'issues.status',
                'issues.workflow_state_id',
                'projects.project_type_id',
            ])
            ->orderBy('issues.id')
            ->chunk(500, function (Collection $issues) use ($lanesByType, $categories): void {
                foreach ($issues as $row) {
                    $id = Cast::int($row->id);
                    $status = Cast::string($row->status);
                    $typeId = Cast::int($row->project_type_id);
                    $lane = $row->workflow_state_id === null ? null : Cast::int($row->workflow_state_id);

                    $target = $lanesByType[$typeId][$status] ?? null;

                    if ($target === null || $target === $lane) {
                        continue;
                    }

                    // Already consistent: the lane it sits in already means this
                    // status, so a different-but-equivalent lane is left alone
                    // rather than moving the issue on the board for no reason.
                    if ($lane !== null && $this->agrees($status, $categories[$lane] ?? '')) {
                        continue;
                    }

                    DB::table('issues')->where('id', $id)->update([
                        'workflow_state_id' => $target,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Not reversible: the lanes these rows held before were wrong or absent,
        // so there is nothing worth restoring them to.
    }

    /**
     * The lane each legacy status maps onto, per project type, mirroring
     * ResolveWorkflowStateAction so the repair agrees with what the API writes.
     *
     * @return array<int, array<string, int>>
     */
    private function lanesByType(): array
    {
        $map = [];

        $states = DB::table('workflow_states')->orderBy('position')->get();

        foreach ($states->groupBy('project_type_id') as $typeId => $lanes) {
            $started = $lanes->where('category', StatusCategory::Started->value)->values();

            $first = fn (string ...$wanted): ?int => ($match = $lanes
                ->first(fn (object $lane): bool => in_array(Cast::string($lane->category), $wanted, true))) === null
                    ? null
                    : Cast::int($match->id);

            $nthStarted = fn (int $index): ?int => ($match = $started->get($index) ?? $started->last()) === null
                ? null
                : Cast::int($match->id);

            $map[Cast::int($typeId)] = array_filter([
                IssueStatus::Backlog->value => $first(StatusCategory::Backlog->value, StatusCategory::Unstarted->value),
                IssueStatus::InProgress->value => $nthStarted(0),
                IssueStatus::InReview->value => $nthStarted(1),
                IssueStatus::Done->value => $first(StatusCategory::Completed->value),
            ], fn (mixed $id): bool => $id !== null);
        }

        return $map;
    }

    /**
     * True when the lane the issue already sits in means the same thing as its
     * status, so a different-but-equivalent lane is not needlessly rewritten.
     */
    private function agrees(string $status, string $category): bool
    {
        return match ($status) {
            IssueStatus::Backlog->value => in_array($category, [StatusCategory::Backlog->value, StatusCategory::Unstarted->value], true),
            IssueStatus::InProgress->value, IssueStatus::InReview->value => $category === StatusCategory::Started->value,
            IssueStatus::Done->value => in_array($category, [StatusCategory::Completed->value, StatusCategory::Canceled->value], true),
            default => false,
        };
    }
};
