<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Estimate against actual, broken down so the numbers can answer a question.
 *
 * The dashboard already had one figure: everything closed this week, every
 * project at once. That cannot say which projects are consistently
 * underestimated, whether the gap is closing, or whether a 4h estimate means
 * something different on TRACK than on CMS.
 *
 * Only issues carrying both an estimate and logged time count. How many were
 * excluded is reported alongside, so a flattering number cannot quietly come
 * from a sample of two.
 */
final class EstimateAccuracy
{
    /**
     * Estimate bands. The interesting question is usually whether large
     * estimates go wrong more often than small ones, not the overall mean.
     *
     * @var array<string, array{0: int, 1: int|null}>
     */
    private const BANDS = [
        'under_1h' => [0, 60],
        '1h_to_4h' => [60, 240],
        'over_4h' => [240, null],
    ];

    /**
     * @return array{
     *     window: int,
     *     overall: array<string, mixed>,
     *     projects: list<array<string, mixed>>,
     *     bands: list<array<string, mixed>>
     * }
     */
    public static function build(User $user, int $weeks): array
    {
        $since = Carbon::now()->startOfWeek()->subWeeks(max(1, $weeks) - 1);

        $issues = Issue::query()
            ->visibleTo($user)
            ->where('status', IssueStatus::Done->value)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $since)
            ->whereNotNull('estimate_minutes')
            ->where('estimate_minutes', '>', 0)
            ->with('project')
            ->withSum('timeEntries', 'minutes')
            ->get();

        $tracked = $issues->filter(
            fn (Issue $issue): bool => Cast::int($issue->getAttribute('time_entries_sum_minutes')) > 0
        );
        $excluded = $issues->count() - $tracked->count();

        return [
            'window' => $weeks,
            'overall' => [
                ...self::summarise($tracked),
                // Named plainly: a number from three issues is not the same
                // claim as one from thirty.
                'excluded' => $excluded,
            ],
            'projects' => self::byProject($tracked),
            'bands' => self::byBand($tracked),
        ];
    }

    /**
     * @param  Collection<int, Issue>  $issues
     * @return array{estimated: int, actual: int, ratio: float|null, direction: string, sampleSize: int}
     */
    private static function summarise(Collection $issues): array
    {
        $estimated = Cast::int($issues->sum('estimate_minutes'));
        $actual = Cast::int($issues->sum(fn (Issue $issue): int => Cast::int($issue->getAttribute('time_entries_sum_minutes'))));

        if ($estimated === 0) {
            return ['estimated' => 0, 'actual' => $actual, 'ratio' => null, 'direction' => 'none', 'sampleSize' => $issues->count()];
        }

        $ratio = round($actual / $estimated, 2);

        return [
            'estimated' => $estimated,
            'actual' => $actual,
            'ratio' => $ratio,
            'direction' => $ratio > 1.0 ? 'over' : ($ratio < 1.0 ? 'under' : 'none'),
            'sampleSize' => $issues->count(),
        ];
    }

    /**
     * @param  Collection<int, Issue>  $issues
     * @return list<array<string, mixed>>
     */
    private static function byProject(Collection $issues): array
    {
        $rows = [];

        foreach ($issues->groupBy(fn (Issue $issue): string => $issue->project->key) as $key => $group) {
            $first = $group->first();

            if (! $first instanceof Issue) {
                continue;
            }

            $rows[] = [
                'key' => Cast::string($key),
                'name' => $first->project->name,
                ...self::summarise($group),
            ];
        }

        // Biggest sample first: the most trustworthy row leads.
        usort($rows, fn (array $a, array $b): int => $b['sampleSize'] <=> $a['sampleSize']);

        return $rows;
    }

    /**
     * @param  Collection<int, Issue>  $issues
     * @return list<array<string, mixed>>
     */
    private static function byBand(Collection $issues): array
    {
        $bands = [];

        foreach (self::BANDS as $label => [$from, $to]) {
            $group = $issues->filter(function (Issue $issue) use ($from, $to): bool {
                $minutes = Cast::int($issue->estimate_minutes);

                return $minutes > $from && ($to === null || $minutes <= $to);
            });

            $bands[] = ['band' => $label, ...self::summarise($group)];
        }

        return $bands;
    }
}
