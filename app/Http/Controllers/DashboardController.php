<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $counts = $this->statusCounts();

        return Inertia::render('Dashboard', [
            'stats' => [
                'open' => $counts['backlog'] + $counts['in_progress'] + $counts['in_review'],
                'in_progress' => $counts['in_progress'],
                'in_review' => $counts['in_review'],
                'done' => $counts['done'],
            ],
            'statusBreakdown' => $counts,
            'activeByProject' => $this->activeByProject(),
            'recent' => $this->recent(),
            'stale' => $this->stale(),
            'inReview' => $this->inReview(),
            'recentlyCompleted' => $this->recentlyCompleted(),
        ]);
    }

    /**
     * @return array{backlog: int, in_progress: int, in_review: int, done: int}
     */
    private function statusCounts(): array
    {
        $count = fn (IssueStatus $status): int => Issue::query()
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
    private function activeByProject(): array
    {
        $rows = Project::query()
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
                'count' => (int) $project->getAttribute('active_count'),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->all();

        return array_values($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recent(): array
    {
        return array_values(Issue::query()
            ->notArchived()
            ->with('project')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Issue $issue): array => $this->row($issue, 'created_at'))
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stale(): array
    {
        return array_values(Issue::query()
            ->notArchived()
            ->where('status', '!=', IssueStatus::Done->value)
            ->with('project')
            ->orderBy('updated_at')
            ->take(6)
            ->get()
            ->map(fn (Issue $issue): array => $this->row($issue, 'updated_at'))
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inReview(): array
    {
        return array_values(Issue::query()
            ->notArchived()
            ->where('status', IssueStatus::InReview->value)
            ->with('project')
            ->latest('updated_at')
            ->take(6)
            ->get()
            ->map(fn (Issue $issue): array => $this->row($issue, 'updated_at'))
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentlyCompleted(): array
    {
        return array_values(Issue::query()
            ->where('status', IssueStatus::Done->value)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subDays(7))
            ->with('project')
            ->orderByDesc('closed_at')
            ->take(6)
            ->get()
            ->map(fn (Issue $issue): array => $this->row($issue, 'closed_at'))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Issue $issue, string $timeColumn): array
    {
        return [
            'identifier' => $issue->identifier,
            'title' => $issue->title,
            'projectColor' => $issue->project->color,
            'status' => $issue->status->value,
            'timestamp' => $issue->{$timeColumn}?->toIso8601String(),
        ];
    }
}
