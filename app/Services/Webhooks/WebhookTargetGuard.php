<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

/**
 * Decides whether a webhook URL may be called, and at which address.
 *
 * Webhooks are called from inside the production host, so this is the SSRF
 * boundary: every address the host resolves to has to be public. The caller
 * connects to the address returned here rather than looking the name up again,
 * so DNS cannot change between the check and the connection.
 */
class WebhookTargetGuard
{
    /**
     * @var list<string>
     */
    private const NON_PUBLIC_V4 = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];

    /**
     * Inside the global unicast block, the special-purpose ranges: IETF protocol
     * assignments (Teredo, benchmarking, ORCHID), documentation, and 6to4.
     *
     * @var list<string>
     */
    private const NON_PUBLIC_V6 = ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'];

    public function __construct(private readonly HostResolver $resolver) {}

    public function resolve(string $url): WebhookTarget
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? mb_strtolower(trim($parts['host'] ?? '', '[]')) : '';

        if ($host === '' || ($parts['scheme'] ?? '') !== 'https') {
            throw new WebhookTargetRefused('The webhook URL is not a valid https URL.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) === false ? $this->resolver->resolve($host) : [$host];

        if ($addresses === []) {
            throw new WebhookTargetRefused('The webhook host does not resolve.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublic($address)) {
                throw new WebhookTargetRefused('The webhook host points at a private or reserved address.');
            }
        }

        return new WebhookTarget($host, $parts['port'] ?? 443, $addresses[0]);
    }

    private function isPublic(string $address): bool
    {
        $packed = filter_var($address, FILTER_VALIDATE_IP) === false ? false : inet_pton($address);

        if ($packed === false) {
            return false;
        }

        $packed = $this->unwrap($packed);

        if (strlen($packed) === 4) {
            return ! $this->withinAny($packed, self::NON_PUBLIC_V4);
        }

        // Only 2000::/3 is global unicast. Everything else (loopback, link-local,
        // unique-local, multicast, site-local) is refused without being listed.
        return $this->within($packed, '2000::/3') && ! $this->withinAny($packed, self::NON_PUBLIC_V6);
    }

    /**
     * @param  list<string>  $ranges
     */
    private function withinAny(string $packed, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($this->within($packed, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An IPv4 address wearing an IPv6 costume (::ffff:a.b.c.d, or the NAT64
     * prefix) is checked as the IPv4 address it is.
     */
    private function unwrap(string $packed): string
    {
        if (strlen($packed) !== 16) {
            return $packed;
        }

        $mapped = str_repeat("\0", 10)."\xff\xff";
        $nat64 = "\x00\x64\xff\x9b".str_repeat("\0", 8);

        if (str_starts_with($packed, $mapped) || str_starts_with($packed, $nat64)) {
            return substr($packed, 12);
        }

        return $packed;
    }

    private function within(string $packed, string $range): bool
    {
        [$base, $bits] = explode('/', $range);
        $packedBase = (string) inet_pton($base);

        $bytes = intdiv((int) $bits, 8);
        $remainder = (int) $bits % 8;

        if (substr($packed, 0, $bytes) !== substr($packedBase, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($packedBase[$bytes]) & $mask);
    }
}
