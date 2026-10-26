<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;

/**
 * Fee periods and membership dates are calendar days of the site, not instants: they are
 * kept as midnight of that day in the display zone, whatever zone the database stores in.
 */
final class CalendarDate
{
    /** Midnight of a `Y-m-d` day (any time part is dropped) in the display zone. */
    public static function parse(string $day): DateTimeImmutable
    {
        return new DateTimeImmutable(substr($day, 0, 10), app(TimezoneServiceInterface::class)->displayTimezone());
    }

    /**
     * An instant typed or sent as text: a string with an offset keeps it, one without is a
     * wall time of the display zone.
     */
    public static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, app(TimezoneServiceInterface::class)->displayTimezone());
    }

    /** Midnight of the current display day. */
    public static function today(): DateTimeImmutable
    {
        return app(TimezoneServiceInterface::class)->today();
    }
}
