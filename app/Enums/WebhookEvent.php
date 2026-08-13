<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookEvent: string
{
    case Created = 'issue.created';
    case StatusChanged = 'issue.status_changed';
    case Assigned = 'issue.assigned';
    case Archived = 'issue.archived';
    case Restored = 'issue.restored';
    case Commented = 'issue.commented';

    /**
     * What an endpoint is subscribed to when it says nothing. Matches what
     * every endpoint received before events were selectable, so adding this
     * column changed nobody's deliveries.
     *
     * @return list<string>
     */
    public static function defaults(): array
    {
        return [self::StatusChanged->value];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $event): string => $event->value, self::cases());
    }
}
