<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Events;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Membership;

final readonly class MembershipExpiring
{
    public function __construct(
        public string $membershipId,
        public string $memberId,
        public DateTimeImmutable $endDate,
        public int $daysUntilExpiration,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(Membership $membership, int $daysUntilExpiration): self
    {
        return new self(
            membershipId: $membership->id->value(),
            memberId: $membership->memberId->value(),
            endDate: $membership->endDate,
            daysUntilExpiration: $daysUntilExpiration,
            occurredAt: new DateTimeImmutable(),
        );
    }
}
