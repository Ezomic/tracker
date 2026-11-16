<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use Illuminate\Container\Attributes\Bind;

#[Bind(DnsHostResolver::class)]
interface HostResolver
{
    /**
     * Every address the name resolves to, v4 and v6, as text. Empty when it does not resolve.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
