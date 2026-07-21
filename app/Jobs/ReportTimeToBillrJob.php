<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ReportTimeToBillrAction;
use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReportTimeToBillrJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Issue $issue,
        public readonly int $minutes,
        public readonly ?string $clientName,
    ) {}

    public function handle(ReportTimeToBillrAction $action): void
    {
        $action->handle($this->issue, $this->minutes, $this->clientName);
    }

    /**
     * Back off before retrying a flaky or briefly-unreachable Billr.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }
}
