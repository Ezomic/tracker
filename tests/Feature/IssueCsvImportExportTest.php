<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Actions\ExportIssuesToCsvAction;
use App\Actions\ImportIssuesFromCsvAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;

function writeTempCsv(array $rows): string
{
    $path = sys_get_temp_dir().'/tracker-csv-test-'.uniqid().'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, ['identifier', 'team', 'number', 'title', 'type', 'status', 'description', 'branch_name', 'github_pr_url', 'closed_at', 'created_at', 'phase']);

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);

    return $path;
}

it('imports issues and creates a new team with next_number seeded from the max imported number', function () {
    $path = writeTempCsv([
        ['TRACK-1', 'TRACK', '1', 'First ticket', 'feature', 'backlog', 'desc one', 'feature/TRACK-1-first-ticket', '', '', '2026-07-07', 'Phase 1'],
        ['TRACK-2', 'TRACK', '2', 'Second ticket', 'fix', 'done', '', 'feature/TRACK-2-second-ticket', 'https://github.com/x/y/pull/1', '2026-07-08', '2026-07-07', 'Phase 1'],
    ]);

    $result = (new ImportIssuesFromCsvAction)->handle($path);

    expect($result)->toMatchArray(['imported' => 2, 'skipped' => 0, 'errors' => []]);

    $team = Project::query()->where('key', 'TRACK')->first();
    expect($team)->not->toBeNull()
        ->and($team->name)->toBe('TRACK')
        ->and($team->next_number)->toBe(2);

    $second = Issue::query()->where('identifier', 'TRACK-2')->first();
    expect($second->type)->toBe(IssueType::Fix)
        ->and($second->status)->toBe(IssueStatus::Done)
        ->and($second->description)->toBeNull()
        ->and($second->github_pr_url)->toBe('https://github.com/x/y/pull/1')
        ->and($second->closed_at)->not->toBeNull();

    unlink($path);
});

it('reports an error and skips a row with an unescaped comma instead of crashing', function () {
    $path = sys_get_temp_dir().'/tracker-csv-test-'.uniqid().'.csv';
    file_put_contents($path, implode("\n", [
        'identifier,team,number,title,type,status,description,branch_name,github_pr_url,closed_at,created_at,phase',
        'TRACK-1,TRACK,1,Bad title, with a comma,feature,backlog,,feature/TRACK-1-bad-title,,,2026-07-07,Phase 1',
        'TRACK-2,TRACK,2,Good title,feature,backlog,,feature/TRACK-2-good-title,,,2026-07-07,Phase 1',
    ]));

    $result = (new ImportIssuesFromCsvAction)->handle($path);

    expect($result['imported'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'])->toHaveCount(1)
        ->and(Issue::query()->where('identifier', 'TRACK-2')->exists())->toBeTrue();

    unlink($path);
});

it('is idempotent - re-importing the same CSV skips already-imported identifiers', function () {
    $path = writeTempCsv([
        ['TRACK-1', 'TRACK', '1', 'First ticket', 'feature', 'backlog', '', 'feature/TRACK-1-first-ticket', '', '', '2026-07-07', 'Phase 1'],
    ]);

    (new ImportIssuesFromCsvAction)->handle($path);
    $result = (new ImportIssuesFromCsvAction)->handle($path);

    expect($result)->toMatchArray(['imported' => 0, 'skipped' => 1]);
    expect(Issue::query()->count())->toBe(1);

    unlink($path);
});

it('skips a row with an invalid type or status without failing the whole import', function () {
    $path = writeTempCsv([
        ['TRACK-1', 'TRACK', '1', 'Good ticket', 'feature', 'backlog', '', 'feature/TRACK-1-good-ticket', '', '', '2026-07-07', 'Phase 1'],
        ['TRACK-2', 'TRACK', '2', 'Bad ticket', 'chore', 'backlog', '', 'feature/TRACK-2-bad-ticket', '', '', '2026-07-07', 'Phase 1'],
    ]);

    $result = (new ImportIssuesFromCsvAction)->handle($path);

    expect($result['imported'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'])->toHaveCount(1);

    unlink($path);
});

it('exports issues to a CSV that can be re-imported into a fresh set of tables', function () {
    $team = Project::factory()->create(['key' => 'THI', 'name' => 'Thijssen Software']);
    (new CreateIssueAction)->handle($team, 'Exported issue', IssueType::Feature, 'a description');

    $exportPath = sys_get_temp_dir().'/tracker-export-test-'.uniqid().'.csv';
    $count = (new ExportIssuesToCsvAction)->handle($exportPath);

    expect($count)->toBe(1);

    Issue::query()->delete();
    Project::query()->delete();

    $result = (new ImportIssuesFromCsvAction)->handle($exportPath);
    expect($result['imported'])->toBe(1);

    $reimported = Issue::query()->where('identifier', 'THI-1')->first();
    expect($reimported)->not->toBeNull()
        ->and($reimported->title)->toBe('Exported issue')
        ->and($reimported->description)->toBe('a description');

    unlink($exportPath);
});
