<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Events;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Membership;

final readonly class MembershipCancelled
{
    public function __construct(
        public string $membershipId,
        public string $memberId,
        public DateTimeImmutable $cancelledAt,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(Membership $membership): self
    {
        return new self(
            membershipId: $membership->id->value(),
            memberId: $membership->memberId->value(),
            cancelledAt: $membership->cancelledAt ?? new DateTimeImmutable(),
            occurredAt: new DateTimeImmutable(),
        );
    }
}
