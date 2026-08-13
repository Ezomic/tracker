<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\CurrentOrganization;
use App\Support\Cast;
use App\Support\Staleness;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const TREND_WEEKS = 8;

    private const TOP_PROJECTS = 6;

    private ?Organization $organization = null;

    public function index(Request $request, CurrentOrganization $current): Response
    {
        $user = $this->currentUser($request);
        $this->organization = $current->for($user);
        $counts = $this->statusCounts($user);

        $done = $this->completedIssues($user);
        $weekly = $this->weeklyCompletions($done);

        return Inertia::render('Dashboard', [
            'stats' => [
                'open' => $counts['backlog'] + $counts['in_progress'] + $counts['in_review'],
                'in_progress' => $counts['in_progress'],
                'in_review' => $counts['in_review'],
                'done' => $counts['done'],
                'archived' => Issue::query()->visibleTo($user)->inOrganization($this->organization)->whereNotNull('archived_at')->count(),
                'urgentOpen' => $this->urgentOpen($user),
            ],
            'statusBreakdown' => $counts,
            'hasProjects' => $user->projects()->notArchived()->exists(),
            'activeByProject' => $this->activeByProject($user),
            'attention' => $this->attention($user),
            'stale' => $this->stale($user),
            'completedByWeek' => $weekly['payload'],
            'metrics' => $this->metrics($counts, $weekly['totals'], $weekly['cycles']),
            'time' => $this->time($user, $done),
        ]);
    }

    /**
     * @return array{backlog: int, in_progress: int, in_review: int, done: int}
     */
    private function statusCounts(User $user): array
    {
        $counts = $this->scoped($user)
            ->notArchived()
            ->groupBy('status')
            ->selectRaw('status, count(*) as aggregate')
            ->pluck('aggregate', 'status');

        return [
            'backlog' => Cast::int($counts->get(IssueStatus::Backlog->value)),
            'in_progress' => Cast::int($counts->get(IssueStatus::InProgress->value)),
            'in_review' => Cast::int($counts->get(IssueStatus::InReview->value)),
            'done' => Cast::int($counts->get(IssueStatus::Done->value)),
        ];
    }

    private function urgentOpen(User $user): int
    {
        return $this->scoped($user)
            ->notArchived()
            ->where('status', '!=', IssueStatus::Done->value)
            ->where('priority', IssuePriority::Urgent->value)
            ->count();
    }

    /**
     * Active (open, non-archived) ticket counts per project, capped at the top
     * projects with the rest folded into a single "Other" entry so the donut
     * and legend stay readable when many projects are in flight.
     *
     * @return list<array<string, mixed>>
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
                'other' => false,
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values();

        $rest = $rows->slice(self::TOP_PROJECTS);

        if ($rest->isEmpty()) {
            return array_values($rows->all());
        }

        return [
            ...$rows->take(self::TOP_PROJECTS)->all(),
            [
                'key' => 'other',
                'name' => 'Other ('.$rest->count().')',
                'color' => null,
                'count' => Cast::int($rest->sum('count')),
                'other' => true,
            ],
        ];
    }

    /**
     * Open issues owned by or assigned to the user, highest priority first, then stalest.
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
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'medium' then 2 when 'low' then 3 else 4 end")
            ->orderBy('updated_at')
            ->take(6)
            ->get()
            ->map(fn (Issue $issue): array => $this->row($issue))
            ->all());
    }

    /**
     * Open issues nobody has touched for longer than their project's threshold.
     * Surfacing only: nothing is archived and nobody is notified, because an
     * issue being old is information rather than an event.
     *
     * @return list<array<string, mixed>>
     */
    private function stale(User $user): array
    {
        return array_values(Staleness::scope($this->scoped($user))
            ->with('project')
            ->take(8)
            ->get()
            ->map(function (Issue $issue): array {
                $last = Staleness::lastActivityAt($issue);

                return [
                    ...$this->row($issue),
                    'quietSince' => $last->toIso8601String(),
                    'quietDays' => (int) $last->diffInDays(now()),
                ];
            })
            ->sortByDesc('quietDays')
            ->values()
            ->all());
    }

    /**
     * Done issues closed within the trailing window, with their project and the
     * total time logged against them.
     *
     * Archived issues are deliberately included: completion is a historical fact
     * keyed on closed_at, and archiving is board cleanup that must not erase a
     * week's throughput. (Current-state figures elsewhere stay non-archived.)
     *
     * @return EloquentCollection<int, Issue>
     */
    private function completedIssues(User $user): EloquentCollection
    {
        return $this->scoped($user)
            ->where('status', IssueStatus::Done->value)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $this->windowStart())
            ->with('project:id,key,name,color')
            ->withSum('timeEntries as logged_minutes', 'minutes')
            ->get(['id', 'project_id', 'created_at', 'closed_at', 'estimate_minutes']);
    }

    /**
     * Fold the completed issues into weekly per-project buckets, capped at the
     * top projects with the rest rolled into an "Other" series.
     *
     * @param  EloquentCollection<int, Issue>  $done
     * @return array{payload: array<string, mixed>, totals: list<int>, cycles: list<float|null>}
     */
    private function weeklyCompletions(EloquentCollection $done): array
    {
        $weeks = [];
        $totals = [];
        $cycles = [];
        /** @var array<int, array{key: string, name: string, color: string, values: list<int>, total: int}> $projects */
        $projects = [];

        for ($i = 0; $i < self::TREND_WEEKS; $i++) {
            $weekStart = CarbonImmutable::now()->startOfWeek()->subWeeks(self::TREND_WEEKS - 1 - $i);
            $weekEnd = $weekStart->addWeek();

            $inWeek = $done->filter(fn (Issue $issue): bool => $issue->closed_at !== null
                && $issue->closed_at >= $weekStart
                && $issue->closed_at < $weekEnd);

            $weeks[] = $weekStart->format('M j');
            $totals[] = $inWeek->count();
            $cycles[] = $this->medianCycleDays($inWeek);

            foreach ($inWeek->groupBy('project_id') as $group) {
                $issue = $group->first();
                $projectId = Cast::int($issue?->project_id);

                if (! isset($projects[$projectId])) {
                    $project = $issue?->project;
                    $projects[$projectId] = [
                        'key' => (string) $project?->key,
                        'name' => (string) $project?->name,
                        'color' => (string) $project?->color,
                        'values' => array_fill(0, self::TREND_WEEKS, 0),
                        'total' => 0,
                    ];
                }

                $projects[$projectId]['values'][$i] = $group->count();
                $projects[$projectId]['total'] += $group->count();
            }
        }

        return [
            'payload' => [
                'weeks' => $weeks,
                'series' => $this->rollUpSeries($projects),
                'weekTotals' => $totals,
                'grandTotal' => array_sum($totals),
            ],
            'totals' => $totals,
            'cycles' => $cycles,
        ];
    }

    /**
     * @param  array<int, array{key: string, name: string, color: string, values: list<int>, total: int}>  $projects
     * @return list<array<string, mixed>>
     */
    private function rollUpSeries(array $projects): array
    {
        $ranked = collect($projects)
            ->sortByDesc('total')
            ->values();

        $series = $ranked
            ->take(self::TOP_PROJECTS)
            ->map(fn (array $project): array => [...$project, 'other' => false])
            ->values()
            ->all();

        $rest = $ranked->slice(self::TOP_PROJECTS);

        if ($rest->isEmpty()) {
            return array_values($series);
        }

        $values = array_fill(0, self::TREND_WEEKS, 0);

        foreach ($rest as $project) {
            foreach ($project['values'] as $week => $count) {
                $values[$week] += $count;
            }
        }

        $series[] = [
            'key' => 'other',
            'name' => 'Other ('.$rest->count().')',
            'color' => null,
            'values' => $values,
            'total' => array_sum($values),
            'other' => true,
        ];

        return array_values($series);
    }

    /**
     * @param  array{backlog: int, in_progress: int, in_review: int, done: int}  $counts
     * @param  list<int>  $totals
     * @param  list<float|null>  $cycles
     * @return array<string, mixed>
     */
    private function metrics(array $counts, array $totals, array $cycles): array
    {
        $current = self::TREND_WEEKS - 1;
        $previous = self::TREND_WEEKS - 2;

        $cycleSpark = array_values(array_filter(
            $cycles,
            fn (?float $value): bool => $value !== null,
        ));

        return [
            'completed' => $totals[$current],
            'completedDelta' => $this->percentDelta($totals[$current], $totals[$previous]),
            'wip' => $counts['in_progress'] + $counts['in_review'],
            'cycleDays' => $cycles[$current],
            'cycleDelta' => $this->pointDelta($cycles[$current], $cycles[$previous]),
            'completedSpark' => $totals,
            'cycleSpark' => $cycleSpark,
        ];
    }

    /**
     * Time logged this week vs last, split by project, plus estimate accuracy
     * across the completed issues in the window.
     *
     * @param  EloquentCollection<int, Issue>  $done
     * @return array<string, mixed>
     */
    private function time(User $user, EloquentCollection $done): array
    {
        $weekStart = CarbonImmutable::now()->startOfWeek();
        $weekEnd = $weekStart->addWeek();
        $previousStart = $weekStart->subWeek();

        $issueIds = $this->scoped($user)->notArchived()->pluck('id');

        $entries = TimeEntry::query()
            ->whereIn('issue_id', $issueIds)
            ->where('spent_on', '>=', $previousStart)
            ->where('spent_on', '<', $weekEnd)
            ->with('issue.project:id,key,name,color')
            ->get(['id', 'issue_id', 'minutes', 'spent_on']);

        $thisWeek = $entries->filter(fn (TimeEntry $entry): bool => $entry->spent_on >= $weekStart);

        return [
            'loggedThisWeek' => Cast::int($thisWeek->sum('minutes')),
            'loggedPreviousWeek' => Cast::int($entries
                ->filter(fn (TimeEntry $entry): bool => $entry->spent_on < $weekStart)
                ->sum('minutes')),
            'loggedByProject' => $this->loggedByProject($thisWeek),
            'accuracy' => $this->estimateAccuracy($done),
        ];
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return list<array{key: string, name: string, color: string, minutes: int}>
     */
    private function loggedByProject(Collection $entries): array
    {
        $rows = $entries
            ->groupBy(fn (TimeEntry $entry): int => Cast::int($entry->issue?->project_id))
            ->map(function (Collection $group): array {
                $project = $group->first()?->issue?->project;

                return [
                    'key' => (string) $project?->key,
                    'name' => (string) $project?->name,
                    'color' => (string) $project?->color,
                    'minutes' => Cast::int($group->sum('minutes')),
                ];
            })
            ->sortByDesc('minutes')
            ->take(5)
            ->values()
            ->all();

        return array_values($rows);
    }

    /**
     * @param  EloquentCollection<int, Issue>  $done
     * @return array{pct: int|null, overPct: int|null, direction: string, sampleSize: int}
     */
    private function estimateAccuracy(EloquentCollection $done): array
    {
        $tracked = $done->filter(fn (Issue $issue): bool => Cast::int($issue->estimate_minutes) > 0
            && Cast::int($issue->getAttribute('logged_minutes')) > 0);

        $estimated = Cast::int($tracked->sum('estimate_minutes'));
        $actual = Cast::int($tracked->sum(fn (Issue $issue): int => Cast::int($issue->getAttribute('logged_minutes'))));

        if ($tracked->isEmpty() || $estimated === 0) {
            return ['pct' => null, 'overPct' => null, 'direction' => 'none', 'sampleSize' => 0];
        }

        $overPct = (int) round((($actual - $estimated) / $estimated) * 100);

        return [
            'pct' => max(0, 100 - abs($overPct)),
            'overPct' => $overPct,
            'direction' => $actual > $estimated ? 'over' : ($actual < $estimated ? 'under' : 'none'),
            'sampleSize' => $tracked->count(),
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

                return $created->diffInDays($closed, true);
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

        // Staleness is measured from the last recorded activity, not from
        // updated_at. updated_at moves on any column write: the TRACK-222
        // backfill touched workflow_state_id on 354 issues and would have
        // silently reset every one of them to "fresh".
        $quietDays = $issue->status === IssueStatus::Done
            ? 0
            : (int) Staleness::lastActivityAt($issue)->diffInDays(CarbonImmutable::now());

        return [
            'identifier' => $issue->identifier,
            'title' => $issue->title,
            'projectName' => $issue->project->name,
            'projectColor' => $issue->project->color,
            'status' => $issue->status->value,
            'priority' => $issue->priority->value,
            'ageDays' => $ageDays,
            'stale' => $issue->status !== IssueStatus::Done
                && $quietDays >= Staleness::daysFor($issue->project),
            'timestamp' => $reference?->toIso8601String(),
        ];
    }
}
