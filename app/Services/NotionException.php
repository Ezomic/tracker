<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class NotionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
