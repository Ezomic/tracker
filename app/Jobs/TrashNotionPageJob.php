<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Notion;
use App\Services\NotionException;
use App\Services\NotionRateLimited;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class TrashNotionPageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $maxExceptions = 3;

    public function __construct(public readonly string $pageId) {}

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

    public function handle(Notion $notion): void
    {
        try {
            $notion->trashPage($this->pageId);
        } catch (NotionRateLimited $limited) {
            $this->release($limited->retryAfter);
        } catch (NotionException $e) {
            if ($e->status !== 404) {
                throw $e;
            }
        }
    }
}
