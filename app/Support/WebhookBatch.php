<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Project;

/**
 * The webhook delivery budget of one operation that can fan out. It lives
 * exactly as long as that operation, so a count can never outlive the work it
 * was counting.
 */
final class WebhookBatch
{
    private int $dispatched = 0;

    /** @var array<int, array{project: Project, count: int}> */
    private array $suppressed = [];

    public function __construct(private readonly int $cap) {}

    /**
     * Whether one more issue's deliveries fit. One that does not is counted
     * against its project: a selection can span projects, and a summary only
     * means something to the endpoints of the project that lost deliveries.
     */
    public function admit(Project $project): bool
    {
        if ($this->dispatched < $this->cap) {
            $this->dispatched++;

            return true;
        }

        $this->suppressed[$project->id] ??= ['project' => $project, 'count' => 0];
        $this->suppressed[$project->id]['count']++;

        return false;
    }

    /**
     * @return array<int, array{project: Project, count: int}>
     */
    public function suppressed(): array
    {
        return $this->suppressed;
    }
}
