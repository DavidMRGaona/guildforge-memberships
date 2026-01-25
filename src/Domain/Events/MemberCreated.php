<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Events;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Member;

final readonly class MemberCreated
{
    public function __construct(
        public string $memberId,
        public string $memberNumber,
        public string $firstName,
        public string $lastName,
        public string $memberType,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(Member $member): self
    {
        return new self(
            memberId: $member->id->value(),
            memberNumber: $member->memberNumber->value,
            firstName: $member->firstName,
            lastName: $member->lastName,
            memberType: $member->memberType->value,
            occurredAt: new DateTimeImmutable(),
        );
    }
}
