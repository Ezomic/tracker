<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ProjectWebhook;
use App\Services\Webhooks\WebhookTargetGuard;
use App\Services\Webhooks\WebhookTargetRefused;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeliverWebhookAction
{
    public const SIGNATURE_HEADER = 'X-Tracker-Signature-256';

    public const EVENT_HEADER = 'X-Tracker-Event';

    public function __construct(private readonly WebhookTargetGuard $guard) {}

    /**
     * POST a signed payload to the endpoint. The signature is computed over the
     * exact bytes sent, in the same shape VerifyGithubWebhookSignature checks
     * on the way in, so a consumer can verify it the same way.
     *
     * Throws on a non-2xx so the queue retries; the outcome is recorded on the
     * webhook either way, which is what the settings UI reports.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(ProjectWebhook $webhook, string $event, array $payload): void
    {
        // Checked again at delivery, not only at save: the name may resolve
        // somewhere else by now.
        try {
            $target = $this->guard->resolve($webhook->url);
        } catch (WebhookTargetRefused $refused) {
            $webhook->forceFill([
                'last_delivered_at' => now(),
                'last_status' => null,
                'last_error' => $refused->getMessage(),
            ])->save();

            throw $refused;
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            self::EVENT_HEADER => $event,
            self::SIGNATURE_HEADER => 'sha256='.hash_hmac('sha256', $body, $webhook->secret),
        ])
            // A redirect would lead the request past the address check.
            ->withoutRedirecting()
            ->withOptions([
                // An environment proxy would resolve the name itself and ignore the pin.
                'proxy' => '',
                'curl' => [CURLOPT_RESOLVE => [$target->pin()]],
            ])
            ->timeout(10)
            ->withBody($body, 'application/json')
            ->post($webhook->url);

        $webhook->forceFill([
            'last_delivered_at' => now(),
            'last_status' => $response->status(),
            'last_error' => $response->successful() ? null : mb_substr($response->body(), 0, 255),
        ])->save();

        if (! $response->successful()) {
            throw new RuntimeException("Webhook delivery to {$webhook->url} failed with {$response->status()}.");
        }
    }
}
