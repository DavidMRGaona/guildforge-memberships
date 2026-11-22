<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Infrastructure\Activity;

use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Infrastructure\Activity\MemberYears;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MemberYearsTest extends TestCase
{
    /**
     * @return array<string, array{MembershipStatus, string, string, string, list<int>}>
     */
    public static function periods(): array
    {
        return [
            'a calendar year' => [MembershipStatus::Expired, '2024-01-01', '2024-12-31', '2026-06-01', [2024]],
            'an academic year spans two' => [MembershipStatus::Expired, '2024-09-01', '2025-06-30', '2026-06-01', [2024, 2025]],
            'the last day of the year counts' => [MembershipStatus::Expired, '2024-12-31', '2024-12-31', '2026-06-01', [2024]],
            'one day in the next year counts' => [MembershipStatus::Expired, '2024-12-31', '2025-01-01', '2026-06-01', [2024, 2025]],
            'several years' => [MembershipStatus::Expired, '2022-03-10', '2025-03-09', '2026-06-01', [2022, 2023, 2024, 2025]],
            'an active period stops at today' => [MembershipStatus::Active, '2025-09-01', '2027-06-30', '2026-06-01', [2025, 2026]],
            'the day it starts counts' => [MembershipStatus::Active, '2027-01-01', '2027-12-31', '2027-01-01', [2027]],
            'a period that has not started counts for nothing' => [MembershipStatus::Active, '2027-01-01', '2027-12-31', '2026-12-31', []],
            'an ended period stops at its end, not today' => [MembershipStatus::Active, '2024-01-01', '2024-12-31', '2030-01-01', [2024]],
            'pending never counts' => [MembershipStatus::Pending, '2024-01-01', '2024-12-31', '2026-06-01', []],
            'cancelled never counts' => [MembershipStatus::Cancelled, '2024-01-01', '2024-12-31', '2026-06-01', []],
            'an end before the start counts for nothing' => [MembershipStatus::Active, '2024-06-01', '2024-01-01', '2026-06-01', []],
        ];
    }

    /**
     * @param  list<int>  $expected
     */
    #[DataProvider('periods')]
    public function test_years_a_period_counts_for(MembershipStatus $status, string $start, string $end, string $today, array $expected): void
    {
        $this->assertSame($expected, MemberYears::covered($status, $start, $end, $today));
    }

    public function test_the_first_day_of_a_year_is_the_start_of_the_period_when_it_begins_inside_it(): void
    {
        $this->assertSame('2025-03-10', MemberYears::firstDay(2025, '2025-03-10'));
        $this->assertSame('2025-01-01', MemberYears::firstDay(2025, '2024-09-01'));
    }

    public function test_the_subject_id_is_the_account_and_the_year(): void
    {
        $id = MemberYears::subjectId('0192A3B4-C5D6-7E8F-9A0B-1C2D3E4F5A6B', 2025);

        $this->assertSame('0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b:2025', $id);
        $this->assertSame(['0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b', 2025], MemberYears::parseSubjectId($id));
        $this->assertSame(['0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b', 2025], MemberYears::parseSubjectId(strtoupper($id)));
    }

    #[DataProvider('unreadableIds')]
    public function test_an_id_that_is_not_an_account_and_a_year_is_not_read(string $id): void
    {
        $this->assertNull(MemberYears::parseSubjectId($id));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableIds(): array
    {
        return [
            'empty' => [''],
            'only the account' => ['0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b'],
            'not an account' => ['abc:2025'],
            'no year' => ['0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b:'],
            'a year that is not one' => ['0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b:20x5'],
            'a year of five digits' => ['0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b:20255'],
            'a trailing newline' => ["0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b:2025\n"],
        ];
    }
}
