<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Entities;

use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Exceptions\InvalidMembershipTransitionException;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;

final class Membership
{
    public function __construct(
        public readonly MembershipId $id,
        public readonly MemberId $memberId,
        public MembershipPeriodType $periodType,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public MembershipStatus $status,
        public ?DateTimeImmutable $activatedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?string $notes = null,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function activate(?DateTimeImmutable $at = null): void
    {
        if ($this->status === MembershipStatus::Active) {
            throw InvalidMembershipTransitionException::alreadyActive();
        }

        $this->status = MembershipStatus::Active;
        $this->activatedAt = $at ?? new DateTimeImmutable();
    }

    public function expire(): void
    {
        $this->status = MembershipStatus::Expired;
    }

    public function cancel(?DateTimeImmutable $at = null): void
    {
        $this->status = MembershipStatus::Cancelled;
        $this->cancelledAt = $at ?? new DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    public function isWithinPeriod(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $now >= $this->startDate && $now <= $this->endDate;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $now > $this->endDate;
    }
}
