<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use DateTimeImmutable;
use Modules\Memberships\Application\Services\ProrationCalculatorInterface;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\ValueObjects\Money;

final readonly class ProrationCalculator implements ProrationCalculatorInterface
{
    private const int ACADEMIC_START_MONTH = 9;

    public function __construct(
        private int $academicStartMonth = self::ACADEMIC_START_MONTH,
    ) {}

    public function calculate(
        Money $fullAmount,
        MembershipPeriodType $periodType,
        DateTimeImmutable $startDate,
        ?array $customRules = null
    ): Money {
        // If custom rules are provided, use them
        if ($customRules !== null) {
            return $this->calculateWithCustomRules($fullAmount, $startDate, $customRules);
        }

        // Calculate remaining months in the period
        $remainingMonths = $this->calculateRemainingMonths($periodType, $startDate);
        $totalMonths = $this->getTotalMonths($periodType);

        if ($remainingMonths >= $totalMonths) {
            return $fullAmount;
        }

        // Calculate prorated amount
        $proratedCents = (int) round(($fullAmount->amount() * $remainingMonths) / $totalMonths);

        return Money::fromCents($proratedCents, $fullAmount->currency());
    }

    private function calculateWithCustomRules(Money $fullAmount, DateTimeImmutable $startDate, array $customRules): Money
    {
        $month = (int) $startDate->format('n');

        // Custom rules should be an array with month => percentage
        // e.g., ['1' => 100, '2' => 91.67, '3' => 83.33, ...]
        if (isset($customRules[$month])) {
            $percentage = (float) $customRules[$month];
            $proratedCents = (int) round(($fullAmount->amount() * $percentage) / 100);

            return Money::fromCents($proratedCents, $fullAmount->currency());
        }

        // If no rule for this month, return full amount
        return $fullAmount;
    }

    private function calculateRemainingMonths(MembershipPeriodType $periodType, DateTimeImmutable $startDate): int
    {
        $month = (int) $startDate->format('n');

        return match ($periodType) {
            MembershipPeriodType::CalendarYear => 13 - $month, // January = 12 months, December = 1 month
            MembershipPeriodType::AcademicYear => $this->calculateAcademicRemainingMonths($month),
            MembershipPeriodType::Rolling => 12, // Rolling always gets full period
        };
    }

    private function calculateAcademicRemainingMonths(int $month): int
    {
        // Academic year: September to August
        // September = 12 months, August = 1 month
        if ($month >= $this->academicStartMonth) {
            // September (9) to December (12): 9 = 12, 10 = 11, 11 = 10, 12 = 9
            return 12 - ($month - $this->academicStartMonth);
        }

        // January (1) to August (8): 1 = 8, 2 = 7, ..., 8 = 1
        return $this->academicStartMonth - 1 - $month;
    }

    private function getTotalMonths(MembershipPeriodType $periodType): int
    {
        return match ($periodType) {
            MembershipPeriodType::CalendarYear => 12,
            MembershipPeriodType::AcademicYear => 12,
            MembershipPeriodType::Rolling => 12,
        };
    }
}
