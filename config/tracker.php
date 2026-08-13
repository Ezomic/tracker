<?php

declare(strict_types=1);

return [
    /*
     * How long an open issue may sit without any recorded activity before the
     * dashboard calls it stale. Overridable per project; null on a project
     * falls back to this rather than meaning "never".
     */
    'stale_after_days' => (int) env('TRACKER_STALE_AFTER_DAYS', 30),
];
