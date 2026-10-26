<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Entities;

use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Domain\ValueObjects\MembershipFeeId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Domain\ValueObjects\Money;

final class MembershipFee
{
    public function __construct(
        public readonly MembershipFeeId $id,
        public readonly MembershipId $membershipId,
        public Money $amount,
        public DateTimeImmutable $dueDate,
        public ?DateTimeImmutable $paidAt = null,
        public ?PaymentMethod $paymentMethod = null,
        public ?string $transactionReference = null,
        public ?string $notes = null,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public function recordPayment(
        ?PaymentMethod $paymentMethod = null,
        ?string $transactionReference = null,
        ?DateTimeImmutable $paidAt = null,
    ): void {
        $this->paidAt = $paidAt ?? new DateTimeImmutable;
        $this->paymentMethod = $paymentMethod;
        $this->transactionReference = $transactionReference;
    }

    public function isPaid(): bool
    {
        return $this->paidAt instanceof DateTimeImmutable;
    }

    /**
     * The due date is a calendar day: the fee is overdue from the day after it, reading the
     * instant in the zone the date carries (the display zone).
     */
    public function isOverdue(DateTimeImmutable $now): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        return $now->setTimezone($this->dueDate->getTimezone())->format('Y-m-d') > $this->dueDate->format('Y-m-d');
    }
}
