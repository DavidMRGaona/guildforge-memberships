<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

use Modules\Memberships\Domain\Entities\MembershipFee;

final readonly class MembershipFeeDTO
{
    public function __construct(
        public string $id,
        public string $membershipId,
        public int $amountCents,
        public string $currency,
        public string $formattedAmount,
        public string $dueDate,
        public ?string $paidAt,
        public ?string $paymentMethod,
        public ?string $paymentMethodLabel,
        public ?string $transactionReference,
        public ?string $notes,
        public ?string $createdAt,
        public bool $isPaid,
        public bool $isOverdue,
    ) {
    }

    public static function fromEntity(MembershipFee $fee): self
    {
        return new self(
            id: $fee->id->value,
            membershipId: $fee->membershipId->value,
            amountCents: $fee->amount->amount,
            currency: $fee->amount->currency,
            formattedAmount: (string) $fee->amount,
            dueDate: $fee->dueDate->format('Y-m-d'),
            paidAt: $fee->paidAt?->format('c'),
            paymentMethod: $fee->paymentMethod?->value,
            paymentMethodLabel: $fee->paymentMethod?->label(),
            transactionReference: $fee->transactionReference,
            notes: $fee->notes,
            createdAt: $fee->createdAt?->format('c'),
            isPaid: $fee->isPaid(),
            isOverdue: $fee->isOverdue(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'membership_id' => $this->membershipId,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'formatted_amount' => $this->formattedAmount,
            'due_date' => $this->dueDate,
            'paid_at' => $this->paidAt,
            'payment_method' => $this->paymentMethod,
            'payment_method_label' => $this->paymentMethodLabel,
            'transaction_reference' => $this->transactionReference,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'is_paid' => $this->isPaid,
            'is_overdue' => $this->isOverdue,
        ];
    }
}
