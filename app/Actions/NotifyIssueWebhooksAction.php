<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\DeliverWebhookJob;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectWebhook;

class NotifyIssueWebhooksAction
{
    /**
     * Queue a delivery to every active endpoint on the issue's project. The
     * payload carries source and external_ref so a consumer can match it to
     * its own record without keeping a tracker identifier around.
     */
    /**
     * Deliveries dispatched during this request. A bulk change across a large
     * selection, or the auto-archive command, would otherwise fan out to one
     * delivery per issue per endpoint. Past the cap the rest are dropped and
     * counted, and a single summary delivery says so, which is more useful to
     * a consumer than a truncated flood it cannot tell was truncated.
     */
    private static int $dispatched = 0;

    private static int $suppressed = 0;

    public const PER_REQUEST_CAP = 50;

    public function handle(Issue $issue, string $event): void
    {
        $webhooks = ProjectWebhook::query()
            ->where('project_id', $issue->project_id)
            ->where('active', true)
            ->get()
            ->filter(fn (ProjectWebhook $webhook): bool => $webhook->wants($event));

        if ($webhooks->isEmpty()) {
            return;
        }

        if (self::$dispatched >= self::PER_REQUEST_CAP) {
            self::$suppressed++;

            return;
        }

        $payload = $this->payload($issue, $event);

        foreach ($webhooks as $webhook) {
            DeliverWebhookJob::dispatch($webhook, $event, $payload);
        }

        self::$dispatched++;
    }

    /**
     * Called once a bulk operation finishes: tells every endpoint that more
     * happened than it was told about, rather than leaving it with a silently
     * partial picture.
     */
    public function flushSuppressed(Project $project): void
    {
        if (self::$suppressed === 0) {
            return;
        }

        $count = self::$suppressed;
        self::reset();

        $webhooks = ProjectWebhook::query()
            ->where('project_id', $project->id)
            ->where('active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            DeliverWebhookJob::dispatch($webhook, 'issue.bulk_changed', [
                'event' => 'issue.bulk_changed',
                'project' => $project->key,
                'suppressed' => $count,
                'sent_at' => now()->toIso8601String(),
            ]);
        }
    }

    public static function reset(): void
    {
        self::$dispatched = 0;
        self::$suppressed = 0;
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
