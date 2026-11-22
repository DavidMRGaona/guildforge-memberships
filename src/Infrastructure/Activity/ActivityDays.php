<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Activity;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeInterface;

/** Calendar days of the display zone, as `Y-m-d`, for the years as a member (activity contract). */
final class ActivityDays
{
    /** The current display day. */
    public static function today(): string
    {
        return app(TimezoneServiceInterface::class)->today()->format('Y-m-d');
    }

    /** The display day an instant falls on, null for none. */
    public static function of(?DateTimeInterface $instant): ?string
    {
        return $instant instanceof DateTimeInterface ? app(TimezoneServiceInterface::class)->toDisplay($instant)->format('Y-m-d') : null;
    }
}
