<?php

declare(strict_types=1);

namespace App\Services;

class NotionRateLimited extends NotionException
{
    public function __construct(string $message, int $status, public readonly int $retryAfter)
    {
        parent::__construct($message, $status);
    }
}
