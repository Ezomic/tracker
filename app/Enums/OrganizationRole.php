<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    /**
     * An outsider. Implies nothing org-wide: guests only ever see what they
     * hold an explicit project grant for.
     */
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
            self::Guest => 'Guest',
        };
    }

    /**
     * Can manage the organization itself: settings, members, templates, labels.
     */
    public function manages(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
