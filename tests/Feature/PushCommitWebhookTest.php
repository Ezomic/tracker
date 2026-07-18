<?php

declare(strict_types=1);

use App\Actions\CreateIssueAction;
use App\Enums\IssueType;
use App\Enums\ProjectLevel;
use App\Models\Commit;
use App\Models\Project;

beforeEach(function () {
    config(['services.github.webhook_secret' => 'test-secret']);
});

function pushPayload(string $branch, array $commits): array
{
    return [
        'ref' => "refs/heads/{$branch}",
        'repository' => ['full_name' => 'owner/repo'],
        'commits' => $commits,
    ];
}

function sendPush($test, array $payload)
{
    $body = json_encode($payload);

    return $test->postJson('/api/webhooks/github', $payload, [
        'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
        'X-GitHub-Event' => 'push',
    ]);
}

it('records commits pushed to an issue branch', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $payload = pushPayload($issue->branch_name, [[
        'id' => 'a1b2c3d4e5f6a1b2c3d4',
        'message' => "fix null case\n\nmore detail",
        'author' => ['name' => 'Robbin'],
        'url' => 'https://github.com/owner/repo/commit/a1b2c3d4e5f6a1b2c3d4',
        'timestamp' => '2026-07-18T10:00:00Z',
    ]]);

    sendPush($this, $payload)->assertNoContent();

    $commit = Commit::query()->firstOrFail();
    expect($commit->issue_id)->toBe($issue->id)
        ->and($commit->sha)->toBe('a1b2c3d4e5f6a1b2c3d4')
        ->and($commit->repository)->toBe('owner/repo')
        ->and($commit->message)->toBe("fix null case\n\nmore detail")
        ->and($commit->author_name)->toBe('Robbin');
});

it('is idempotent on re-delivery', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    $payload = pushPayload($issue->branch_name, [[
        'id' => 'abc123abc123',
        'message' => 'work',
        'author' => ['name' => 'Robbin'],
        'timestamp' => '2026-07-18T10:00:00Z',
    ]]);

    sendPush($this, $payload);
    sendPush($this, $payload);

    expect(Commit::query()->count())->toBe(1);
});

it('ignores pushes to branches with no matching issue', function () {
    Project::factory()->create(['key' => 'THI']);

    sendPush($this, pushPayload('main', [[
        'id' => 'deadbeef',
        'message' => 'unrelated',
        'author' => ['name' => 'Robbin'],
        'timestamp' => '2026-07-18T10:00:00Z',
    ]]))->assertNoContent();

    expect(Commit::query()->count())->toBe(0);
});

it('rejects a push with an invalid signature', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);

    $this->postJson('/api/webhooks/github', pushPayload($issue->branch_name, []), [
        'X-Hub-Signature-256' => 'sha256=nope',
        'X-GitHub-Event' => 'push',
    ])->assertUnauthorized();

    expect(Commit::query()->count())->toBe(0);
});

it('shows commits in the issue timeline', function () {
    $project = Project::factory()->create(['key' => 'THI']);
    $user = member($project, ProjectLevel::Write);
    $issue = (new CreateIssueAction)->handle($project, 'Task', IssueType::Feature);
    Commit::factory()->for($issue)->create([
        'sha' => 'feed1234beef5678',
        'message' => 'wire it up',
    ]);

    $this->actingAs($user)->get("/issues/{$issue->identifier}")
        ->assertInertia(fn ($page) => $page
            ->where(
                'timeline',
                fn ($timeline) => collect($timeline)->contains(
                    fn ($item) => $item['kind'] === 'commit'
                        && $item['shortSha'] === 'feed123'
                        && $item['message'] === 'wire it up'
                )
            )
        );
});
