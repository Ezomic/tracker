<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use App\Models\WorkflowState;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class ImportIssuesFromCsvAction
{
    public function __construct(
        private readonly MoveIssueToStateAction $move = new MoveIssueToStateAction,
        private readonly ResolveWorkflowStateAction $resolve = new ResolveWorkflowStateAction,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function handle(string $path): array
    {
        // Every imported row can fire issue.created, so the whole file is one
        // webhook batch: its deliveries share one cap and a summary.
        return NotifyIssueWebhooksAction::batch(fn (): array => $this->import($path));
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    private function import(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file at [{$path}].");
        }

        $header = fgetcsv($handle, escape: '');

        if ($header === false) {
            throw new RuntimeException("Unable to read a header row from [{$path}].");
        }

        $header = array_map(fn (?string $cell): string => (string) $cell, $header);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $teamNextNumbers = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $rowNumber++;
            $row = array_map(fn (?string $cell): string => (string) $cell, $row);

            if (count($row) !== count($header)) {
                $errors[] = "Skipped row {$rowNumber}: expected ".count($header).' columns, got '.count($row).' (check for an unescaped comma in a field).';
                $skipped++;

                continue;
            }

            $data = array_combine($header, $row);

            if (Issue::query()->where('identifier', $data['identifier'])->exists()) {
                $skipped++;

                continue;
            }

            try {
                $team = Project::query()->firstOrCreate(
                    ['key' => $data['team']],
                    ['name' => $data['team']],
                );

                $number = (int) $data['number'];

                $issue = new Issue;
                $issue->timestamps = false;
                $issue->forceFill([
                    'project_id' => $team->id,
                    'number' => $number,
                    'identifier' => $data['identifier'],
                    'title' => $data['title'],
                    'slug' => (string) str($data['title'])->slug(),
                    'description' => $data['description'] !== '' ? $data['description'] : null,
                    'type' => IssueType::from($data['type']),
                    // The export carries no priority. The column defaults to
                    // none, but the created observer's webhook payload reads
                    // it from this model before any refresh.
                    'priority' => IssuePriority::None,
                    'status' => IssueStatus::from($data['status']),
                    'branch_name' => $data['branch_name'],
                    'github_pr_url' => $data['github_pr_url'] !== '' ? $data['github_pr_url'] : null,
                    'closed_at' => $data['closed_at'] !== '' ? Carbon::parse($data['closed_at']) : null,
                    'created_at' => $data['created_at'] !== '' ? Carbon::parse($data['created_at']) : now(),
                    'updated_at' => now(),
                ])->save();

                $state = $this->resolve->handle($team, $issue->status);

                if ($state instanceof WorkflowState) {
                    $this->move->handle($issue, $state);
                }

                $teamNextNumbers[$team->id] = max($teamNextNumbers[$team->id] ?? $team->next_number, $number);
                $imported++;
            } catch (Throwable $e) {
                $errors[] = "Skipped {$data['identifier']}: {$e->getMessage()}";
                $skipped++;
            }
        }

        fclose($handle);

        foreach ($teamNextNumbers as $teamId => $maxNumber) {
            Project::query()->where('id', $teamId)->update(['next_number' => $maxNumber]);
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }
}
