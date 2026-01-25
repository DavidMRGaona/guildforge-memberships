<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Enums;

use DateTimeImmutable;

enum MembershipPeriodType: string
{
    case CalendarYear = 'calendar_year';
    case AcademicYear = 'academic_year';
    case Rolling = 'rolling';

    /**
     * Get period start and end dates based on reference date.
     *
     * @param  int  $academicStartMonth  Month when academic year starts (default: September = 9)
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    public function getPeriodDates(DateTimeImmutable $referenceDate, int $academicStartMonth = 9): array
    {
        return match ($this) {
            self::CalendarYear => $this->getCalendarYearDates($referenceDate),
            self::AcademicYear => $this->getAcademicYearDates($referenceDate, $academicStartMonth),
            self::Rolling => $this->getRollingDates($referenceDate),
        };
    }

    /**
     * Get human-readable label for the period type.
     */
    public function label(): string
    {
        $key = match ($this) {
            self::CalendarYear => 'memberships::memberships.enums.membership_period_type.calendar_year',
            self::AcademicYear => 'memberships::memberships.enums.membership_period_type.academic_year',
            self::Rolling => 'memberships::memberships.enums.membership_period_type.rolling',
        };

        if (! function_exists('app') || ! app()->bound('translator')) {
            return $key;
        }

        return __($key);
    }

    /**
     * Get all period type values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get calendar year dates (January 1 to December 31).
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    private function getCalendarYearDates(DateTimeImmutable $referenceDate): array
    {
        $year = (int) $referenceDate->format('Y');

        return [
            'start' => new DateTimeImmutable("{$year}-01-01"),
            'end' => new DateTimeImmutable("{$year}-12-31"),
        ];
    }

    /**
     * Get academic year dates (September 1 to June 30).
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    private function getAcademicYearDates(DateTimeImmutable $referenceDate, int $academicStartMonth): array
    {
        $year = (int) $referenceDate->format('Y');
        $month = (int) $referenceDate->format('n');

        // If reference date is before academic start month, we're in the second half
        // of the previous academic year
        if ($month < $academicStartMonth) {
            $startYear = $year - 1;
        } else {
            $startYear = $year;
        }

        $endYear = $startYear + 1;
        $startMonth = str_pad((string) $academicStartMonth, 2, '0', STR_PAD_LEFT);

        // Academic year ends in June (month 6) of the following year
        return [
            'start' => new DateTimeImmutable("{$startYear}-{$startMonth}-01"),
            'end' => new DateTimeImmutable("{$endYear}-06-30"),
        ];
    }

    /**
     * Get rolling dates (one year from reference date).
     *
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
     */
    private function getRollingDates(DateTimeImmutable $referenceDate): array
    {
        $endDate = $referenceDate->modify('+1 year -1 day');

        return [
            'start' => $referenceDate,
            'end' => $endDate,
        ];
    }
}
