<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\DeliverWebhookJob;
use App\Models\Issue;
use App\Models\ProjectWebhook;
use App\Support\WebhookBatch;

class NotifyIssueWebhooksAction
{
    /**
     * Issue events one batch delivers. A bulk change across a large selection,
     * or an archive sweep, would otherwise fan out to one delivery per issue per
     * endpoint. Past the cap the rest are counted rather than sent, and the
     * batch closes with a summary saying so, which is more useful to a consumer
     * than a truncated flood it cannot tell was truncated.
     */
    public const PER_BATCH_CAP = 50;

    /**
     * Only ever set inside batch(), which always clears it again, so nothing a
     * long-lived worker or Octane process did earlier can count against later
     * work.
     */
    private static ?WebhookBatch $batch = null;

    /**
     * Run an operation that can touch many issues as one batch: its deliveries
     * share one cap, and it always ends with a summary for each project that
     * lost some, even when the work throws partway.
     *
     * Outside a batch nothing is capped. A change to one issue cannot fan out,
     * and a caller that forgets to open a batch then shows up as too many
     * deliveries rather than silently none. A batch opened inside another
     * joins it, so nesting cannot hand out a second budget.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $work
     * @return TReturn
     */
    public static function batch(callable $work): mixed
    {
        if (self::$batch instanceof WebhookBatch) {
            return $work();
        }

        $batch = self::$batch = new WebhookBatch(self::PER_BATCH_CAP);

        try {
            return $work();
        } finally {
            self::$batch = null;
            self::summarize($batch);
        }
    }

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
            ->get()
            ->filter(fn (ProjectWebhook $webhook): bool => $webhook->wants($event));

        if ($webhooks->isEmpty()) {
            return;
        }

        if (self::$batch instanceof WebhookBatch && ! self::$batch->admit($issue->project)) {
            return;
        }

        $payload = $this->payload($issue, $event);

        foreach ($webhooks as $webhook) {
            DeliverWebhookJob::dispatch($webhook, $event, $payload);
        }
    }

    /**
     * Tell every endpoint of a project that lost deliveries how many, rather
     * than leaving it with a silently partial picture.
     */
    private static function summarize(WebhookBatch $batch): void
    {
        foreach ($batch->suppressed() as ['project' => $project, 'count' => $count]) {
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
