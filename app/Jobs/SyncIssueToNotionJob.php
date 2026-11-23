<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncIssueToNotionAction;
use App\Models\Issue;
use App\Services\NotionRateLimited;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncIssueToNotionJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $maxExceptions = 3;

    public function __construct(public readonly Issue $issue) {}

    /**
     * Rate limits are waited out for as long as Notion asks, which is why the
     * job is bounded by time rather than by attempts: a release counts as an
     * attempt. Real errors still give up after three.
     */
    public function retryUntil(): CarbonImmutable
    {
        return now()->addMinutes(30);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    /**
     * One waiting sync per issue is enough: the job reads the issue when it
     * runs, so a second change before then is already covered.
     */
    public function uniqueId(): string
    {
        return (string) $this->issue->id;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("notion-issue-{$this->issue->id}"))->releaseAfter(10)->expireAfter(120)];
    }

    public function handle(SyncIssueToNotionAction $action): void
    {
        try {
            $action->handle($this->issue);
        } catch (NotionRateLimited $limited) {
            $this->release($limited->retryAfter);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->issue->recordActivity('notion_sync_failed', [
            'message' => mb_substr($exception->getMessage(), 0, 255),
        ]);
    }
}
