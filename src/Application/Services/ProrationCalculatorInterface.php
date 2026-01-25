<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\Services;

use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\ValueObjects\Money;

interface ProrationCalculatorInterface
{
    /**
     * Calculates the prorated fee based on the start date within the period.
     *
     * @param Money $fullAmount The full annual/period fee
     * @param MembershipPeriodType $periodType The type of membership period
     * @param DateTimeImmutable $startDate When the membership starts
     * @param array<string, mixed>|null $customRules Optional custom proration rules
     * @return Money The prorated fee amount
     */
    public function calculate(
        Money $fullAmount,
        MembershipPeriodType $periodType,
        DateTimeImmutable $startDate,
        ?array $customRules = null
    ): Money;
}
