<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * Can edit project settings and manage members.
     */
    public function manages(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
