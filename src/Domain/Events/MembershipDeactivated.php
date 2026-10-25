<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Events;

use DateTimeImmutable;

/**
 * An active membership stopped being active: its status changed (expired,
 * cancelled, back to pending), it was deleted or it moved to another member.
 */
final readonly class MembershipDeactivated
{
    public function __construct(
        public string $membershipId,
        public string $memberId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
