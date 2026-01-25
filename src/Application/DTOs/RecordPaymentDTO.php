<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

final readonly class RecordPaymentDTO
{
    public function __construct(
        public string $feeId,
        public string $paymentMethod,
        public ?string $transactionReference = null,
        public ?string $paidAt = null,
        public ?string $notes = null,
    ) {
    }

    /**
     * @param  array{fee_id: string, payment_method: string, transaction_reference?: string|null, paid_at?: string|null, notes?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            feeId: $data['fee_id'],
            paymentMethod: $data['payment_method'],
            transactionReference: $data['transaction_reference'] ?? null,
            paidAt: $data['paid_at'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}
