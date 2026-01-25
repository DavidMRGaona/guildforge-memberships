<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Events;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Membership;

final readonly class MembershipCreated
{
    public function __construct(
        public string $membershipId,
        public string $memberId,
        public string $periodType,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(Membership $membership): self
    {
        return new self(
            membershipId: $membership->id->value(),
            memberId: $membership->memberId->value(),
            periodType: $membership->periodType->value,
            startDate: $membership->startDate,
            endDate: $membership->endDate,
            occurredAt: new DateTimeImmutable(),
        );
    }
}
