<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Events;

use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\MemberStatus;

final readonly class MemberStatusChanged
{
    public function __construct(
        public string $memberId,
        public string $previousStatus,
        public string $newStatus,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(string $memberId, MemberStatus $previous, MemberStatus $new): self
    {
        return new self(
            memberId: $memberId,
            previousStatus: $previous->value,
            newStatus: $new->value,
            occurredAt: new DateTimeImmutable(),
        );
    }
}
