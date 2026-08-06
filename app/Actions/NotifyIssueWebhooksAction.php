<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\DeliverWebhookJob;
use App\Models\Issue;
use App\Models\ProjectWebhook;

class NotifyIssueWebhooksAction
{
    /**
     * Queue a delivery to every active endpoint on the issue's project. The
     * payload carries source and external_ref so a consumer can match it to
     * its own record without keeping a tracker identifier around.
     */
    public function handle(Issue $issue, string $event): void
    {
        $webhooks = ProjectWebhook::query()
            ->where('project_id', $issue->project_id)
            ->where('active', true)
            ->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = $this->payload($issue, $event);

        foreach ($webhooks as $webhook) {
            DeliverWebhookJob::dispatch($webhook, $event, $payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Issue $issue, string $event): array
    {
        return [
            'event' => $event,
            'issue' => [
                'identifier' => $issue->identifier,
                'title' => $issue->title,
                'type' => $issue->type->value,
                'status' => $issue->status->value,
                'priority' => $issue->priority->value,
                'url' => url("/issues/{$issue->identifier}"),
                'source' => $issue->source,
                'external_ref' => $issue->external_ref,
                'external_reporter' => $issue->external_reporter,
                'closed_at' => $issue->closed_at?->toIso8601String(),
            ],
            'project' => $issue->project->key,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
