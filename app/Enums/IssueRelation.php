<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How one issue relates to another. Stored one row per direction, so a query
 * from either side is a plain lookup rather than a union of two.
 */
enum IssueRelation: string
{
    case Blocks = 'blocks';
    case BlockedBy = 'blocked_by';
    case Duplicates = 'duplicates';
    case DuplicatedBy = 'duplicated_by';
    case RelatesTo = 'relates_to';

    /**
     * The relation written on the other issue when this one is created, so the
     * pair cannot get out of step.
     */
    public function inverse(): self
    {
        return match ($this) {
            self::Blocks => self::BlockedBy,
            self::BlockedBy => self::Blocks,
            self::Duplicates => self::DuplicatedBy,
            self::DuplicatedBy => self::Duplicates,
            self::RelatesTo => self::RelatesTo,
        };
    }

    /**
     * The directions a person picks from. The inverses exist in the database
     * but are never chosen directly: you say "blocks", the other side gets
     * "blocked by".
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Blocks, self::BlockedBy, self::Duplicates, self::RelatesTo];
    }

    public function label(): string
    {
        return match ($this) {
            self::Blocks => 'Blocks',
            self::BlockedBy => 'Blocked by',
            self::Duplicates => 'Duplicates',
            self::DuplicatedBy => 'Duplicated by',
            self::RelatesTo => 'Relates to',
        };
    }
}
