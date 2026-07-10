<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;
use RuntimeException;

class ExportIssuesToCsvAction
{
    private const COLUMNS = [
        'identifier', 'team', 'number', 'title', 'type', 'status',
        'description', 'branch_name', 'github_pr_url', 'closed_at', 'created_at',
    ];

    public function handle(string $path): int
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Unable to open [{$path}] for writing.");
        }

        fputcsv($handle, self::COLUMNS);
        $count = 0;

        Issue::query()
            ->with('project')
            ->orderBy('project_id')
            ->orderBy('number')
            ->chunk(200, function ($issues) use ($handle, &$count) {
                foreach ($issues as $issue) {
                    fputcsv($handle, [
                        $issue->identifier,
                        $issue->project->key,
                        $issue->number,
                        $issue->title,
                        $issue->type->value,
                        $issue->status->value,
                        $issue->description ?? '',
                        $issue->branch_name,
                        $issue->github_pr_url ?? '',
                        $issue->closed_at?->toIso8601String() ?? '',
                        $issue->created_at?->toIso8601String() ?? '',
                    ]);
                    $count++;
                }
            });

        fclose($handle);

        return $count;
    }
}
