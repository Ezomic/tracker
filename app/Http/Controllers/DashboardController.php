<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\CurrentOrganization;
use App\Support\Cast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const STALE_DAYS = 7;

    private const TREND_WEEKS = 8;

    private ?Organization $organization = null;

    public function index(Request $request, CurrentOrganization $current): Response
    {
        $user = $this->currentUser($request);
        $this->organization = $current->for($user);
        $counts = $this->statusCounts($user);

        return Inertia::render('Dashboard', [
            'stats' => [
                'open' => $counts['backlog'] + $counts['in_progress'] + $counts['in_review'],
                'in_progress' => $counts['in_progress'],
                'in_review' => $counts['in_review'],
                'done' => $counts['done'],
                'archived' => Issue::query()->visibleTo($user)->inOrganization($this->organization)->whereNotNull('archived_at')->count(),
            ],
            'statusBreakdown' => $counts,
            'hasProjects' => $user->projects()->notArchived()->exists(),
            'activeByProject' => $this->activeByProject($user),
            'attention' => $this->attention($user),
            'board' => $this->board($user),
            'trend' => $this->trend($user),
            'metrics' => $this->metrics($user, $counts),
        ]);
    }

    /**
     * @return array{backlog: int, in_progress: int, in_review: int, done: int}
     */
    private function statusCounts(User $user): array
    {
        $count = fn (IssueStatus $status): int => $this->scoped($user)
            ->notArchived()
            ->where('status', $status->value)
            ->count();

        return [
            'backlog' => $count(IssueStatus::Backlog),
            'in_progress' => $count(IssueStatus::InProgress),
            'in_review' => $count(IssueStatus::InReview),
            'done' => $count(IssueStatus::Done),
        ];
    }

    /**
     * @return list<array{key: string, name: string, color: string, count: int}>
     */
    private function activeByProject(User $user): array
    {
        $rows = Project::query()
            ->visibleTo($user)->inOrganization($this->organization)->notArchived()
            ->withCount(['issues as active_count' => fn (Builder $query) => $query
                ->whereNull('archived_at')
                ->where('status', '!=', IssueStatus::Done->value)])
            ->orderByDesc('active_count')
            ->orderBy('key')
            ->get()
            ->map(fn (Project $project): array => [
                'key' => $project->key,
                'name' => $project->name,
                'color' => $project->color,
                'count' => Cast::int($project->getAttribute('active_count')),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->all();

        return array_values($rows);
    }

    /**
     * Open issues owned by or assigned to the user, stalest first.
     *
     * @return list<array<string, mixed>>
     */
    private function attention(User $user): array
    {
        return array_values($this->scoped($user)
            ->notArchived()
            ->where('status', '!=', IssueStatus::Done->value)
            ->where(fn (Builder $query) => $query
                ->where('owner_id', $user->id)
                ->orWhere('assignee_id', $user->id))
            ->with('project')
            ->orderBy('updated_at')
            ->take(6)
            ->get()
            ->map(fn (Issue $issue): array => $this->row($issue))
            ->all());
    }

    /**
     * @return array{backlog: list<array<string, mixed>>, in_progress: list<array<string, mixed>>, in_review: list<array<string, mixed>>, done: list<array<string, mixed>>}
     */
    private function board(User $user): array
    {
        $column = fn (IssueStatus $status, string $order): array => array_values($this->scoped($user)
            ->when($status !== IssueStatus::Done, fn (Builder $query) => $query->notArchived())
            ->where('status', $status->value)
            ->with('project')
            ->orderByDesc($order)
            ->take(5)
            ->get()
            ->map(fn (Issue $issue): array => $this->row($issue))
            ->all());

        return [
            'backlog' => $column(IssueStatus::Backlog, 'updated_at'),
            'in_progress' => $column(IssueStatus::InProgress, 'updated_at'),
            'in_review' => $column(IssueStatus::InReview, 'updated_at'),
            'done' => $column(IssueStatus::Done, 'closed_at'),
        ];
    }

    /**
     * Weekly opened vs completed counts over the trailing window.
     *
     * @return list<array{label: string, opened: int, completed: int, cycle: float|null}>
     */
    private function trend(User $user): array
    {
        $start = $this->windowStart();

        $opened = $this->scoped($user)
            ->where('created_at', '>=', $start)
            ->pluck('created_at');

        $completed = $this->scoped($user)
            ->where('status', IssueStatus::Done->value)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $start)
            ->get(['created_at', 'closed_at']);

        $weeks = [];

        for ($i = self::TREND_WEEKS - 1; $i >= 0; $i--) {
            $weekStart = CarbonImmutable::now()->startOfWeek()->subWeeks($i);
            $weekEnd = $weekStart->addWeek();

            $openedCount = $opened
                ->filter(fn (mixed $date): bool => $date instanceof CarbonImmutable && $date >= $weekStart && $date < $weekEnd)
                ->count();

            $completedInWeek = $completed
                ->filter(fn (Issue $issue): bool => $issue->closed_at >= $weekStart && $issue->closed_at < $weekEnd);

            $weeks[] = [
                'label' => $weekStart->format('M j'),
                'opened' => $openedCount,
                'completed' => $completedInWeek->count(),
                'cycle' => $this->medianCycleDays($completedInWeek),
            ];
        }

        return $weeks;
    }

    /**
     * @param  array{backlog: int, in_progress: int, in_review: int, done: int}  $counts
     * @return array<string, mixed>
     */
    private function metrics(User $user, array $counts): array
    {
        $weeks = $this->trend($user);
        $current = $weeks[self::TREND_WEEKS - 1];
        $previous = $weeks[self::TREND_WEEKS - 2];

        $cycles = array_values(array_filter(
            array_map(fn (array $week): ?float => $week['cycle'], $weeks),
            fn (?float $value): bool => $value !== null,
        ));

        return [
            'completed' => $current['completed'],
            'completedDelta' => $this->percentDelta($current['completed'], $previous['completed']),
            'opened' => $current['opened'],
            'openedDelta' => $this->percentDelta($current['opened'], $previous['opened']),
            'wip' => $counts['in_progress'] + $counts['in_review'],
            'cycleDays' => $current['cycle'],
            'cycleDelta' => $this->pointDelta($current['cycle'], $previous['cycle']),
            'completedSpark' => array_map(fn (array $week): int => $week['completed'], $weeks),
            'openedSpark' => array_map(fn (array $week): int => $week['opened'], $weeks),
            'cycleSpark' => $cycles,
        ];
    }

    /**
     * @param  Collection<int, Issue>  $issues
     */
    private function medianCycleDays(Collection $issues): ?float
    {
        $median = $issues
            ->map(function (Issue $issue): ?float {
                $created = $issue->created_at;
                $closed = $issue->closed_at;

                if ($created === null || $closed === null) {
                    return null;
                }

                return (float) $created->diffInDays($closed, true);
            })
            ->filter(fn (?float $days): bool => $days !== null)
            ->median();

        return $median === null ? null : round((float) $median, 1);
    }

    private function percentDelta(int $current, int $previous): int
    {
        if ($previous === 0) {
            return $current === 0 ? 0 : 100;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function pointDelta(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return round($current - $previous, 1);
    }

    private function windowStart(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfWeek()->subWeeks(self::TREND_WEEKS - 1);
    }

    /**
     * @return Builder<Issue>
     */
    private function scoped(User $user): Builder
    {
        return Issue::query()->visibleTo($user)->inOrganization($this->organization);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Issue $issue): array
    {
        $reference = $issue->status === IssueStatus::Done
            ? $issue->closed_at
            : $issue->updated_at;

        $ageDays = $reference !== null
            ? (int) CarbonImmutable::now()->diffInDays($reference, true)
            : 0;

        return [
            'identifier' => $issue->identifier,
            'title' => $issue->title,
            'projectName' => $issue->project->name,
            'projectColor' => $issue->project->color,
            'status' => $issue->status->value,
            'ageDays' => $ageDays,
            'stale' => $issue->status !== IssueStatus::Done && $ageDays >= self::STALE_DAYS,
            'timestamp' => $reference?->toIso8601String(),
        ];
    }
}
