<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Events;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Membership;

/**
 * A membership has moved to expired, whoever owns it: the memberships of permanent member
 * types (permanent_member_types, honorary by default) expire too, although their role does
 * not depend on them. A listener that notifies the member must leave those types out, as
 * the expiry warnings and the overdue fee events do.
 */
final readonly class MembershipExpired
{
    public function __construct(
        public string $membershipId,
        public string $memberId,
        public DateTimeImmutable $endDate,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(Membership $membership): self
    {
        return new self(
            membershipId: $membership->id->value(),
            memberId: $membership->memberId->value(),
            endDate: $membership->endDate,
            occurredAt: new DateTimeImmutable(),
        );
    }
}
