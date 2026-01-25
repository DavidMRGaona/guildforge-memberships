<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Enums;

use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use PHPUnit\Framework\TestCase;

final class MembershipPeriodTypeTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = MembershipPeriodType::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(MembershipPeriodType::CalendarYear, $cases);
        $this->assertContains(MembershipPeriodType::AcademicYear, $cases);
        $this->assertContains(MembershipPeriodType::Rolling, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('calendar_year', MembershipPeriodType::CalendarYear->value);
        $this->assertEquals('academic_year', MembershipPeriodType::AcademicYear->value);
        $this->assertEquals('rolling', MembershipPeriodType::Rolling->value);
    }

    public function test_calendar_year_returns_jan_to_dec_dates(): void
    {
        $referenceDate = new DateTimeImmutable('2025-06-15');
        $dates = MembershipPeriodType::CalendarYear->getPeriodDates($referenceDate);

        $this->assertArrayHasKey('start', $dates);
        $this->assertArrayHasKey('end', $dates);
        $this->assertInstanceOf(DateTimeImmutable::class, $dates['start']);
        $this->assertInstanceOf(DateTimeImmutable::class, $dates['end']);

        $this->assertEquals('2025-01-01', $dates['start']->format('Y-m-d'));
        $this->assertEquals('2025-12-31', $dates['end']->format('Y-m-d'));
    }

    public function test_academic_year_returns_sept_to_june_dates(): void
    {
        // Test for reference date in first half of academic year (Sept-Dec)
        $referenceDate = new DateTimeImmutable('2025-10-15');
        $dates = MembershipPeriodType::AcademicYear->getPeriodDates($referenceDate);

        $this->assertArrayHasKey('start', $dates);
        $this->assertArrayHasKey('end', $dates);
        $this->assertInstanceOf(DateTimeImmutable::class, $dates['start']);
        $this->assertInstanceOf(DateTimeImmutable::class, $dates['end']);

        $this->assertEquals('2025-09-01', $dates['start']->format('Y-m-d'));
        $this->assertEquals('2026-06-30', $dates['end']->format('Y-m-d'));
    }

    public function test_academic_year_handles_second_half_correctly(): void
    {
        // Test for reference date in second half of academic year (Jan-June)
        $referenceDate = new DateTimeImmutable('2026-03-15');
        $dates = MembershipPeriodType::AcademicYear->getPeriodDates($referenceDate);

        $this->assertEquals('2025-09-01', $dates['start']->format('Y-m-d'));
        $this->assertEquals('2026-06-30', $dates['end']->format('Y-m-d'));
    }

    public function test_rolling_returns_one_year_from_reference_date(): void
    {
        $referenceDate = new DateTimeImmutable('2025-06-15');
        $dates = MembershipPeriodType::Rolling->getPeriodDates($referenceDate);

        $this->assertArrayHasKey('start', $dates);
        $this->assertArrayHasKey('end', $dates);
        $this->assertInstanceOf(DateTimeImmutable::class, $dates['start']);
        $this->assertInstanceOf(DateTimeImmutable::class, $dates['end']);

        $this->assertEquals('2025-06-15', $dates['start']->format('Y-m-d'));
        $this->assertEquals('2026-06-14', $dates['end']->format('Y-m-d'));
    }

    public function test_rolling_handles_leap_years(): void
    {
        // Test with leap year date
        $referenceDate = new DateTimeImmutable('2024-02-29');
        $dates = MembershipPeriodType::Rolling->getPeriodDates($referenceDate);

        $this->assertEquals('2024-02-29', $dates['start']->format('Y-m-d'));
        $this->assertEquals('2025-02-28', $dates['end']->format('Y-m-d'));
    }

    public function test_it_returns_label(): void
    {
        // This test will fail until the label() method is implemented
        // The label() method should return translated strings from:
        // memberships::memberships.enums.membership_period_type.calendar_year
        // memberships::memberships.enums.membership_period_type.academic_year
        // memberships::memberships.enums.membership_period_type.rolling

        $this->assertIsString(MembershipPeriodType::CalendarYear->label());
        $this->assertIsString(MembershipPeriodType::AcademicYear->label());
        $this->assertIsString(MembershipPeriodType::Rolling->label());
    }

    public function test_values_returns_string_values(): void
    {
        $values = MembershipPeriodType::values();

        $this->assertIsArray($values);
        $this->assertContains('calendar_year', $values);
        $this->assertContains('academic_year', $values);
        $this->assertContains('rolling', $values);
    }
}
