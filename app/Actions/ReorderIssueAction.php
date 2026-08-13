<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReorderIssueAction
{
    /**
     * Gap between neighbours. Sparse so dropping an issue between two others
     * writes one row instead of renumbering the lane.
     */
    public const GAP = 1000;

    /**
     * Below this the neighbours are too close to fit anything between them and
     * the lane is spread out again.
     */
    private const MIN_GAP = 2;

    /**
     * Place an issue directly after $afterId in its lane, or at the top when
     * $afterId is null. Moving between lanes is handled by the caller; this
     * only decides where in the lane it lands.
     */
    public function handle(Issue $issue, ?int $afterId): void
    {
        DB::transaction(function () use ($issue, $afterId): void {
            $issue->forceFill(['board_position' => $this->positionFor($issue, $afterId)])->saveQuietly();
        });
    }

    private function positionFor(Issue $issue, ?int $afterId): int
    {
        $lane = $this->lane($issue);

        if ($afterId === null) {
            $first = (clone $lane)->orderBy('board_position')->value('board_position');

            return $first === null ? self::GAP : Cast::int($first) - self::GAP;
        }

        $after = (clone $lane)->where('id', $afterId)->first(['id', 'board_position']);

        if ($after === null) {
            return self::GAP;
        }

        $afterPosition = Cast::int($after->board_position);

        $next = (clone $lane)
            ->where('board_position', '>', $afterPosition)
            ->where('id', '!=', $issue->id)
            ->orderBy('board_position')
            ->value('board_position');

        if ($next === null) {
            return $afterPosition + self::GAP;
        }

        $gap = Cast::int($next) - $afterPosition;

        if ($gap < self::MIN_GAP) {
            $this->rebalance($issue);

            return $this->positionFor($issue->refresh(), $afterId);
        }

        return $afterPosition + intdiv($gap, 2);
    }

    /**
     * Spread a lane back out to even gaps. Only runs when two neighbours have
     * closed to within one, which takes a lot of drops into the same spot.
     */
    private function rebalance(Issue $issue): void
    {
        $position = self::GAP;

        foreach ($this->lane($issue)->orderBy('board_position')->orderBy('id')->pluck('id') as $id) {
            Issue::query()->whereKey($id)->update(['board_position' => $position]);
            $position += self::GAP;
        }
    }

    /**
     * @return Builder<Issue>
     */
    private function lane(Issue $issue): mixed
    {
        $query = Issue::query()->where('project_id', $issue->project_id);

        return $issue->workflow_state_id === null
            ? $query->whereNull('workflow_state_id')
            : $query->where('workflow_state_id', $issue->workflow_state_id);
    }

    /**
     * A newly created or newly moved issue lands at the top of its lane, which
     * is where you look first.
     */
    public function toTop(Issue $issue): void
    {
        $this->handle($issue, null);
    }
}
