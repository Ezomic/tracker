<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A grant on a project. Access is the highest level a user reaches from any
 * source (a direct grant, or their organization role implying admin), so these
 * are ordered: read < write < admin.
 */
enum ProjectLevel: string
{
    case Read = 'read';
    case Write = 'write';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Read',
            self::Write => 'Write',
            self::Admin => 'Admin',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Read => 1,
            self::Write => 2,
            self::Admin => 3,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * The higher of two levels, treating null as no access.
     */
    public static function max(?self $a, ?self $b): ?self
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a->rank() >= $b->rank() ? $a : $b;
    }
}
