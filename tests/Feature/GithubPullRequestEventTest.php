<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Project;

beforeEach(function () {
    config(['services.github.webhook_secret' => 'test-secret']);
});

function signedPost(string $uri, array $data, string $event)
{
    $body = json_encode($data);

    return test()->postJson($uri, $data, [
        'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
        'X-GitHub-Event' => $event,
    ]);
}

it('moves an issue to in_review when its PR is opened', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    signedPost('/api/webhooks/github', [
        'action' => 'opened',
        'pull_request' => [
            'head' => ['ref' => $issue->branch_name],
            'html_url' => 'https://github.com/Ezomic/tracker/pull/42',
            'merged' => false,
        ],
    ], 'pull_request')->assertNoContent();

    expect($issue->fresh())
        ->status->toBe(IssueStatus::InReview)
        ->github_pr_url->toBe('https://github.com/Ezomic/tracker/pull/42');
});

it('moves an issue to done and stamps closed_at when its PR is merged', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    signedPost('/api/webhooks/github', [
        'action' => 'closed',
        'pull_request' => [
            'head' => ['ref' => $issue->branch_name],
            'html_url' => 'https://github.com/Ezomic/tracker/pull/42',
            'merged' => true,
        ],
    ], 'pull_request')->assertNoContent();

    expect($issue->fresh())
        ->status->toBe(IssueStatus::Done)
        ->closed_at->not->toBeNull();
});

it('does not change status when a PR is closed without merging', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    signedPost('/api/webhooks/github', [
        'action' => 'closed',
        'pull_request' => [
            'head' => ['ref' => $issue->branch_name],
            'html_url' => 'https://github.com/Ezomic/tracker/pull/42',
            'merged' => false,
        ],
    ], 'pull_request')->assertNoContent();

    expect($issue->fresh()->status)->toBe(IssueStatus::Backlog);
});

it('no-ops when the branch name does not match any issue', function () {
    signedPost('/api/webhooks/github', [
        'action' => 'opened',
        'pull_request' => [
            'head' => ['ref' => 'some-random-branch'],
            'html_url' => 'https://github.com/Ezomic/tracker/pull/42',
            'merged' => false,
        ],
    ], 'pull_request')->assertNoContent();
});

it('no-ops on a matched branch belonging to a nonexistent issue number', function () {
    Project::factory()->create(['key' => 'THI']);

    signedPost('/api/webhooks/github', [
        'action' => 'opened',
        'pull_request' => [
            'head' => ['ref' => 'feature/THI-999-does-not-exist'],
            'html_url' => 'https://github.com/Ezomic/tracker/pull/42',
            'merged' => false,
        ],
    ], 'pull_request')->assertNoContent();
});

it('ignores non-pull_request events like ping', function () {
    $team = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($team, 'An issue', IssueType::Feature);

    signedPost('/api/webhooks/github', [
        'zen' => 'Responsive is better than fast.',
    ], 'ping')->assertNoContent();

    expect($issue->fresh()->status)->toBe(IssueStatus::Backlog);
});
