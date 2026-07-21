<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\Issue;
use App\Support\Cast;
use Illuminate\Support\Carbon;

class RecordPushedCommitsAction
{
    /**
     * Store the commits from a GitHub push payload against the issue whose
     * identifier is encoded in the pushed branch (e.g. feature/THI-42-slug).
     * Pushes to branches with no matching issue are ignored. Re-deliveries
     * upsert on (repository, sha).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $branch = data_get($payload, 'ref');
        $branch = is_string($branch) ? preg_replace('#^refs/heads/#', '', $branch) : null;
        $identifier = $this->extractIdentifier($branch);
        $repository = data_get($payload, 'repository.full_name');

        if ($identifier === null || ! is_string($repository)) {
            return;
        }

        $issue = Issue::query()->where('identifier', $identifier)->first();

        if ($issue === null) {
            return;
        }

        $commits = $payload['commits'] ?? [];

        foreach (is_array($commits) ? $commits : [] as $commit) {
            if (! is_array($commit)) {
                continue;
            }

            $sha = $commit['id'] ?? null;

            if (! is_string($sha)) {
                continue;
            }

            Commit::updateOrCreate(
                ['repository' => $repository, 'sha' => $sha],
                [
                    'issue_id' => $issue->id,
                    'branch' => $branch,
                    'message' => is_string($commit['message'] ?? null) ? $commit['message'] : '',
                    'author_name' => data_get($commit, 'author.name'),
                    'url' => $commit['url'] ?? null,
                    'committed_at' => isset($commit['timestamp'])
                        ? Carbon::parse(Cast::string($commit['timestamp']))
                        : now(),
                ],
            );
        }
    }

    private function extractIdentifier(?string $branch): ?string
    {
        if ($branch === null) {
            return null;
        }

        return preg_match('#^(?:feature|fix)/([A-Z]{2,10}-\d+)-#', $branch, $matches) === 1
            ? $matches[1]
            : null;
    }
}
