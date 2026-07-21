<?php

declare(strict_types=1);

namespace App\Support;

class Duration
{
    /**
     * Parse a human duration into whole minutes. Accepts "1h 30m", "1h", "30m",
     * "1.5h", or a bare number (minutes). Returns null when the input is empty
     * or cannot be understood.
     */
    public static function toMinutes(?string $input): ?int
    {
        if ($input === null) {
            return null;
        }

        $input = strtolower(trim($input));

        if ($input === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $input) === 1) {
            return (int) $input;
        }

        if (preg_match('/^(?:(\d+(?:\.\d+)?)\s*h)?\s*(?:(\d+)\s*m)?$/', $input, $matches) === 1
            && (($matches[1] ?? '') !== '' || ($matches[2] ?? '') !== '')) {
            $hours = ($matches[1] ?? '') !== '' ? (float) $matches[1] : 0.0;
            $minutes = ($matches[2] ?? '') !== '' ? (int) $matches[2] : 0;

            return (int) round($hours * 60) + $minutes;
        }

        return null;
    }

    /**
     * Format whole minutes as "1h 30m" (or "45m", "2h").
     */
    public static function format(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return match (true) {
            $hours === 0 => "{$remainder}m",
            $remainder === 0 => "{$hours}h",
            default => "{$hours}h {$remainder}m",
        };
    }
}
