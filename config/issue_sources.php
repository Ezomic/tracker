<?php

declare(strict_types=1);

/**
 * Apps that file issues here through the API, keyed by the `source` they send.
 * `url` turns an issue's external_ref back into a link to the originating
 * record; `:ref` is replaced with the reference. A source with no entry still
 * files fine, it just renders as a plain badge with no link.
 */
return [
    'snag' => [
        'label' => 'Snag',
        'url' => env('SNAG_URL', 'https://snag.thijssensoftware.nl').'/reports/:ref',
    ],

    'flare' => [
        'label' => 'Flare',
        'url' => env('FLARE_URL', 'https://flare.thijssensoftware.nl').'/errors/:ref',
    ],
];
