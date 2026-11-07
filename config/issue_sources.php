<?php

declare(strict_types=1);

/**
 * Apps that file issues here through the API, keyed by the `source` they send.
 * `url` turns an issue's external_ref back into a link to the originating
 * record; `:ref` is replaced with the reference. A source with no entry, or
 * whose host env var is empty, still files fine: it just renders as a plain
 * badge with no link.
 */
$link = static fn (mixed $host, string $path): ?string => is_string($host) && trim($host) !== ''
    ? $host.$path
    : null;

return [
    'snag' => [
        'label' => 'Snag',
        'url' => $link(env('SNAG_URL'), '/reports/:ref'),
    ],

    'flare' => [
        'label' => 'Flare',
        'url' => $link(env('FLARE_URL'), '/errors/:ref'),
    ],
];
