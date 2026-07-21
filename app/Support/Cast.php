<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Narrow `mixed` values (request input, Eloquent aggregates, config, cached
 * data) to scalars without an unchecked cast. Returns a safe default when the
 * value is not convertible, rather than casting blindly.
 */
final class Cast
{
    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_map(self::string(...), $value))
            : [];
    }

    /**
     * @return list<int>
     */
    public static function ints(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_map(self::int(...), $value))
            : [];
    }
}
