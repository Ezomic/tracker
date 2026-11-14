<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_AAAA);

        $addresses = array_merge(
            gethostbynamel($host) ?: [],
            is_array($records) ? array_column($records, 'ipv6') : [],
        );

        return array_values(array_unique(array_filter($addresses, is_string(...))));
    }
}
