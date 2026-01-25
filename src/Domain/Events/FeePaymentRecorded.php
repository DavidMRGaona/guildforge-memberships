<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Events;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\MembershipFee;

final readonly class FeePaymentRecorded
{
    public function __construct(
        public string $feeId,
        public string $membershipId,
        public int $amountCents,
        public string $currency,
        public ?string $paymentMethod,
        public DateTimeImmutable $paidAt,
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
            paymentMethod: $fee->paymentMethod?->value,
            paidAt: $fee->paidAt ?? new DateTimeImmutable(),
            occurredAt: new DateTimeImmutable(),
        );
    }
}
