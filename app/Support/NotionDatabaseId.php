<?php

declare(strict_types=1);

namespace App\Support;

final class NotionDatabaseId
{
    private const MAX_LENGTH = 2048;

    private const BARE = '[0-9a-f]{32}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /**
     * The 32 lowercase hex characters of a database id, from a bare id (with or
     * without dashes) or a Notion page URL, or null when the input is neither.
     * Only https URLs on notion.so or notion.site are read, and only the path:
     * a query string carries view ids, not the database.
     */
    public static function normalise(mixed $input): ?string
    {
        if (! is_string($input)) {
            return null;
        }

        $value = trim($input);

        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        $id = preg_match('~^https://~i', $value) === 1
            ? self::fromUrl($value)
            : self::match('~^('.self::BARE.')$~i', $value);

        return $id === null ? null : strtolower(str_replace('-', '', $id));
    }

    private static function fromUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        if ($host !== 'notion.so' && ! str_ends_with($host, '.notion.so') && ! str_ends_with($host, '.notion.site')) {
            return null;
        }

        $segments = explode('/', rtrim($parts['path'] ?? '', '/'));

        return self::match('~(?:^|-)('.self::BARE.')$~i', rawurldecode(end($segments)));
    }

    private static function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $matches) === 1 ? $matches[1] : null;
    }
}
