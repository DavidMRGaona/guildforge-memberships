<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Activity;

use Illuminate\Support\Str;
use Modules\Memberships\Domain\Enums\MembershipStatus;

/**
 * Which calendar years of the display zone a membership period makes its member «a member in»
 * (core#774), and how those years are named as activity subjects.
 *
 * The module's own definition of being a member is a period in force: Active or Expired (an
 * expired one was active until its end). A pending period was never in force and a cancelled one
 * is not counted, whatever it covered. Payments do not matter: the module activates a period
 * explicitly and holds the association role through it, never through a paid fee. A year counts
 * from the first day of the period inside it and never ahead of today. A period expired by hand
 * stops counting the day it was expired (`expired_at`, in the display zone); one without that day
 * expired at its end.
 *
 * A member of a permanent type (honorary by default) holds the role without any period while
 * the record is active, so for as long as it is active each year from the day they joined counts
 * too (`permanent`).
 *
 * Days are `Y-m-d` strings of the display zone: they compare as text.
 */
final class MemberYears
{
    /**
     * @return list<int> the years the period covers up to today, oldest first
     */
    public static function covered(MembershipStatus $status, string $startDate, string $endDate, string $today, ?string $expiredOn = null): array
    {
        if ($status !== MembershipStatus::Active && $status !== MembershipStatus::Expired) {
            return [];
        }

        $start = substr($startDate, 0, 10);
        $last = min(substr($endDate, 0, 10), substr($today, 0, 10));

        if ($status === MembershipStatus::Expired && $expiredOn !== null) {
            $last = min($last, substr($expiredOn, 0, 10));
        }

        if ($start > $last) {
            return [];
        }

        return range((int) substr($start, 0, 4), (int) substr($last, 0, 4));
    }

    /**
     * Every year any of the periods covers with the first day of that year in force, oldest year first.
     *
     * @param  iterable<array{MembershipStatus, string, string, string|null}>  $periods  status, start day, end day and the day it was expired by hand
     * @param  string|null  $permanentSince  the day an active member of a permanent type joined: every year from it counts
     * @return array<int, string> year => `Y-m-d`
     */
    public static function firstDays(iterable $periods, string $today, ?string $permanentSince = null): array
    {
        $days = [];

        foreach ($periods as [$status, $start, $end, $expiredOn]) {
            foreach (self::covered($status, $start, $end, $today, $expiredOn) as $year) {
                $day = self::firstDay($year, $start);

                if (! isset($days[$year]) || $day < $days[$year]) {
                    $days[$year] = $day;
                }
            }
        }

        foreach (self::permanent($permanentSince, $today) as $year) {
            $day = self::firstDay($year, (string) $permanentSince);

            if (! isset($days[$year]) || $day < $days[$year]) {
                $days[$year] = $day;
            }
        }

        ksort($days);

        return $days;
    }

    /**
     * @return list<int> the years from the one the member joined up to today's, none before they joined
     */
    public static function permanent(?string $joinedOn, string $today): array
    {
        if ($joinedOn === null || substr($joinedOn, 0, 10) > substr($today, 0, 10)) {
            return [];
        }

        return range((int) substr($joinedOn, 0, 4), (int) substr($today, 0, 4));
    }

    /** The first day of the year the period is in force, as `Y-m-d` (the period may begin inside it). */
    public static function firstDay(int $year, string $startDate): string
    {
        return max(sprintf('%04d-01-01', $year), substr($startDate, 0, 10));
    }

    /** One subject per account and year: `<account id in lower case>:<year>`. */
    public static function subjectId(string $userId, int $year): string
    {
        return strtolower($userId).':'.$year;
    }

    /**
     * @return array{string, int}|null the account (lower case) and the year, null when it is not a subject id
     */
    public static function parseSubjectId(string $subjectId): ?array
    {
        if (preg_match('/\A([0-9a-fA-F-]{36}):([0-9]{4})\z/', $subjectId, $parts) !== 1 || ! Str::isUuid($parts[1])) {
            return null;
        }

        return [strtolower($parts[1]), (int) $parts[2]];
    }
}
