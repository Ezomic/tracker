<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Models\Issue;

class ApplyGithubPullRequestEventAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $branch = data_get($payload, 'pull_request.head.ref');
        $identifier = $this->extractIdentifier($branch);

        if ($identifier === null) {
            return;
        }

        $issue = Issue::query()->where('identifier', $identifier)->first();

        if ($issue === null) {
            return;
        }

        $action = data_get($payload, 'action');
        $merged = data_get($payload, 'pull_request.merged', false);
        $prUrl = data_get($payload, 'pull_request.html_url');

        if (in_array($action, ['opened', 'reopened'], true)) {
            $issue->forceFill([
                'status' => IssueStatus::InReview,
                'github_pr_url' => $prUrl,
            ])->save();

            $issue->recordActivity('pr_opened', ['url' => $prUrl]);

            return;
        }

        if ($action === 'closed' && $merged === true) {
            $issue->forceFill([
                'status' => IssueStatus::Done,
                'closed_at' => now(),
                'github_pr_url' => $prUrl,
            ])->save();

            $issue->recordActivity('pr_merged', ['url' => $prUrl]);
        }
    }

    private function extractIdentifier(mixed $branch): ?string
    {
        if (! is_string($branch)) {
            return null;
        }

        if (preg_match('#^(?:feature|fix)/([A-Z]{2,10}-\d+)-#', $branch, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
