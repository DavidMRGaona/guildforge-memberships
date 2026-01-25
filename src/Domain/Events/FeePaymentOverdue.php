<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Events;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\MembershipFee;

final readonly class FeePaymentOverdue
{
    public function __construct(
        public string $feeId,
        public string $membershipId,
        public int $amountCents,
        public string $currency,
        public DateTimeImmutable $dueDate,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(MembershipFee $fee): self
    {
        return new self(
            feeId: $fee->id->value(),
            membershipId: $fee->membershipId->value(),
            amountCents: $fee->amount->toCents(),
            currency: $fee->amount->currency(),
            dueDate: $fee->dueDate,
            occurredAt: new DateTimeImmutable(),
        );
    }
}
