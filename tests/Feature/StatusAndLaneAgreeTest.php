<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Actions\ImportIssuesFromCsvAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\StatusCategory;
use App\Models\Issue;
use App\Models\ProjectType;
use App\Models\WorkflowState;

beforeEach(function () {
    config(['services.github.webhook_secret' => 'test-secret']);
});

function laneProject(): array
{
    [$org, $user] = organizationWith();
    $type = ProjectType::factory()->for($org)->create(['is_default' => true]);

    $lanes = collect([
        ['Backlog', StatusCategory::Backlog, 0],
        ['In Progress', StatusCategory::Started, 1],
        ['In Review', StatusCategory::Started, 2],
        ['Done', StatusCategory::Completed, 3],
    ])->mapWithKeys(fn (array $lane): array => [
        $lane[0] => WorkflowState::factory()->for($type)->create(['name' => $lane[0], 'category' => $lane[1], 'position' => $lane[2], 'is_default' => $lane[0] === 'Backlog']),
    ]);

    return [projectInOrganization($org, $user, ['key' => 'LAN', 'project_type_id' => $type->id]), $lanes];
}

function pullRequest(Issue $issue, string $action, bool $merged): void
{
    $data = [
        'action' => $action,
        'pull_request' => ['head' => ['ref' => $issue->branch_name], 'html_url' => 'https://github.com/Ezomic/x/pull/1', 'merged' => $merged],
    ];
    $body = json_encode($data);

    test()->postJson('/api/webhooks/github', $data, [
        'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
        'X-GitHub-Event' => 'pull_request',
    ])->assertNoContent();
}

function legacyOf(Issue $issue): IssueStatus
{
    $lane = WorkflowState::query()->findOrFail($issue->workflow_state_id);

    return match ($lane->category) {
        StatusCategory::Backlog, StatusCategory::Unstarted => IssueStatus::Backlog,
        StatusCategory::Completed, StatusCategory::Canceled => IssueStatus::Done,
        StatusCategory::Started => $lane->name === 'In Progress' ? IssueStatus::InProgress : IssueStatus::InReview,
    };
}

it('moves the board column to In Review when a PR is opened, and to Done when it is merged', function () {
    [$project, $lanes] = laneProject();
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);

    pullRequest($issue, 'opened', false);
    $issue->refresh();

    expect($issue->workflow_state_id)->toBe($lanes['In Review']->id)
        ->and($issue->status)->toBe(IssueStatus::InReview)
        ->and(legacyOf($issue))->toBe($issue->status);

    pullRequest($issue, 'closed', true);
    $issue->refresh();

    expect($issue->workflow_state_id)->toBe($lanes['Done']->id)
        ->and($issue->status)->toBe(IssueStatus::Done)
        ->and($issue->closed_at)->not->toBeNull()
        ->and($issue->github_pr_url)->toBe('https://github.com/Ezomic/x/pull/1')
        ->and(legacyOf($issue))->toBe($issue->status);
});

it('leaves the board column alone when a PR is closed without merging', function () {
    [$project, $lanes] = laneProject();
    $issue = (new CreateIssueAction)->handle($project, 'An issue', IssueType::Feature);

    pullRequest($issue, 'closed', false);

    expect($issue->fresh()->workflow_state_id)->toBe($lanes['Backlog']->id);
});

it('puts an imported issue in the lane its status means', function () {
    [$project, $lanes] = laneProject();
    $path = sys_get_temp_dir().'/lane-import-'.uniqid().'.csv';
    $rows = [
        ['identifier', 'team', 'number', 'title', 'type', 'status', 'description', 'branch_name', 'github_pr_url', 'closed_at', 'created_at', 'phase'],
        ['LAN-1', 'LAN', '1', 'Open one', 'feature', 'backlog', '', 'feature/LAN-1-open-one', '', '', '2026-07-07', ''],
        ['LAN-2', 'LAN', '2', 'In review one', 'feature', 'in_review', '', 'feature/LAN-2-in-review-one', '', '', '2026-07-07', ''],
        ['LAN-3', 'LAN', '3', 'Done one', 'fix', 'done', '', 'feature/LAN-3-done-one', '', '2026-07-08', '2026-07-07', ''],
    ];
    $handle = fopen($path, 'w');

    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '');
    }

    fclose($handle);

    expect((new ImportIssuesFromCsvAction)->handle($path))->toMatchArray(['imported' => 3, 'skipped' => 0]);

    foreach (['LAN-1' => 'Backlog', 'LAN-2' => 'In Review', 'LAN-3' => 'Done'] as $identifier => $lane) {
        $issue = Issue::query()->where('identifier', $identifier)->firstOrFail();

        expect($issue->workflow_state_id)->toBe($lanes[$lane]->id)
            ->and(legacyOf($issue))->toBe($issue->status);
    }

    unlink($path);
});
