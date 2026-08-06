<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DeliverWebhookAction;
use App\Models\ProjectWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly ProjectWebhook $webhook,
        public readonly string $event,
        public readonly array $payload,
    ) {}

    public function handle(DeliverWebhookAction $action): void
    {
        $action->handle($this->webhook, $this->event, $this->payload);
    }

    /**
     * Four attempts over roughly ten minutes, then give up. A consumer that is
     * still down after that is not coming back inside this request's lifetime,
     * and an endless retry would pile the queue up behind it.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }
}
