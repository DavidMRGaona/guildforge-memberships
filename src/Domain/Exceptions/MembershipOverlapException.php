<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Exceptions;

use DateTimeImmutable;
use DomainException;

final class MembershipOverlapException extends DomainException
{
    public static function overlappingPeriod(
        string $memberId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
    ): self {
        return new self(
            sprintf(
                'Member with ID %s already has an overlapping membership for the period %s to %s',
                $memberId,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
            )
        );
    }
}
