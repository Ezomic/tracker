<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class ImportIssuesFromCsvAction
{
    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function handle(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file at [{$path}].");
        }

        $header = fgetcsv($handle, escape: '');

        if ($header === false) {
            throw new RuntimeException("Unable to read a header row from [{$path}].");
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $teamNextNumbers = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $rowNumber++;

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
                    'status' => IssueStatus::from($data['status']),
                    'branch_name' => $data['branch_name'],
                    'github_pr_url' => $data['github_pr_url'] !== '' ? $data['github_pr_url'] : null,
                    'closed_at' => $data['closed_at'] !== '' ? Carbon::parse($data['closed_at']) : null,
                    'created_at' => $data['created_at'] !== '' ? Carbon::parse($data['created_at']) : now(),
                    'updated_at' => now(),
                ])->save();

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
