<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

use Modules\Memberships\Domain\Entities\FeeStructure;

final readonly class FeeStructureDTO
{
    /**
     * @param  array<string, mixed>|null  $prorationRules
     */
    public function __construct(
        public string $id,
        public string $memberType,
        public string $memberTypeLabel,
        public string $periodType,
        public string $periodTypeLabel,
        public int $amountCents,
        public string $currency,
        public string $formattedAmount,
        public ?array $prorationRules,
        public string $validFrom,
        public ?string $validUntil,
        public ?string $description,
        public bool $isDefault,
        public ?string $createdAt,
        public bool $isCurrentlyValid,
    ) {
    }

    public static function fromEntity(FeeStructure $feeStructure): self
    {
        return new self(
            id: $feeStructure->id->value,
            memberType: $feeStructure->memberType->value,
            memberTypeLabel: $feeStructure->memberType->label(),
            periodType: $feeStructure->periodType->value,
            periodTypeLabel: $feeStructure->periodType->label(),
            amountCents: $feeStructure->amount->amount,
            currency: $feeStructure->amount->currency,
            formattedAmount: (string) $feeStructure->amount,
            prorationRules: $feeStructure->prorationRules,
            validFrom: $feeStructure->validFrom->format('Y-m-d'),
            validUntil: $feeStructure->validUntil?->format('Y-m-d'),
            description: $feeStructure->description,
            isDefault: $feeStructure->isDefault,
            createdAt: $feeStructure->createdAt?->format('c'),
            isCurrentlyValid: $feeStructure->isCurrentlyValid(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'member_type' => $this->memberType,
            'member_type_label' => $this->memberTypeLabel,
            'period_type' => $this->periodType,
            'period_type_label' => $this->periodTypeLabel,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'formatted_amount' => $this->formattedAmount,
            'proration_rules' => $this->prorationRules,
            'valid_from' => $this->validFrom,
            'valid_until' => $this->validUntil,
            'description' => $this->description,
            'is_default' => $this->isDefault,
            'created_at' => $this->createdAt,
            'is_currently_valid' => $this->isCurrentlyValid,
        ];
    }
}
