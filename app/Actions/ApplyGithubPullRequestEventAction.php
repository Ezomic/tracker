<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\WorkflowState;

class ApplyGithubPullRequestEventAction
{
    public function __construct(
        private readonly MoveIssueToStateAction $move = new MoveIssueToStateAction,
        private readonly ResolveWorkflowStateAction $resolve = new ResolveWorkflowStateAction,
    ) {}

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
            $this->moveTo($issue, IssueStatus::InReview);
            $issue->forceFill(['github_pr_url' => $prUrl])->save();

            $issue->recordActivity('pr_opened', ['url' => $prUrl]);

            return;
        }

        if ($action === 'closed' && $merged === true) {
            $this->moveTo($issue, IssueStatus::Done);
            $issue->forceFill(['github_pr_url' => $prUrl])->save();

            $issue->recordActivity('pr_merged', ['url' => $prUrl]);
        }
    }

    /**
     * Through the lane when the project has one, so the board column, the
     * status and closed_at move together. A project with no type or no lane
     * for this status keeps writing the status alone.
     */
    private function moveTo(Issue $issue, IssueStatus $status): void
    {
        $state = $this->resolve->handle($issue->project, $status);

        if ($state instanceof WorkflowState) {
            $this->move->handle($issue, $state);

            return;
        }

        $issue->forceFill([
            'status' => $status,
            'closed_at' => $status === IssueStatus::Done ? now() : null,
        ])->save();
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
