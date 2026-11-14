<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

readonly class WebhookTarget
{
    public function __construct(
        public string $host,
        public int $port,
        public string $address,
    ) {}

    /**
     * The curl resolve entry that pins the connection to the checked address.
     */
    public function pin(): string
    {
        $address = str_contains($this->address, ':') ? "[{$this->address}]" : $this->address;

        return "{$this->host}:{$this->port}:{$address}";
    }
}
