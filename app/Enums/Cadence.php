<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum Cadence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * The next occurrence strictly after the given moment.
     */
    public function advance(CarbonImmutable $from): CarbonImmutable
    {
        return match ($this) {
            self::None => $from,
            self::Daily => $from->addDay(),
            self::Weekly => $from->addWeek(),
            self::Monthly => $from->addMonthNoOverflow(),
        };
    }
}
