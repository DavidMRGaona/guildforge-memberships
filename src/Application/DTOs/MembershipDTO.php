<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

use Modules\Memberships\Domain\Entities\Membership;

final readonly class MembershipDTO
{
    public function __construct(
        public string $id,
        public string $memberId,
        public string $periodType,
        public string $periodTypeLabel,
        public string $startDate,
        public string $endDate,
        public string $status,
        public string $statusLabel,
        public ?string $activatedAt,
        public ?string $cancelledAt,
        public ?string $notes,
        public ?string $createdAt,
        public bool $isActive,
        public bool $isExpired,
    ) {
    }

    public static function fromEntity(Membership $membership): self
    {
        return new self(
            id: $membership->id->value,
            memberId: $membership->memberId->value,
            periodType: $membership->periodType->value,
            periodTypeLabel: $membership->periodType->label(),
            startDate: $membership->startDate->format('Y-m-d'),
            endDate: $membership->endDate->format('Y-m-d'),
            status: $membership->status->value,
            statusLabel: $membership->status->label(),
            activatedAt: $membership->activatedAt?->format('c'),
            cancelledAt: $membership->cancelledAt?->format('c'),
            notes: $membership->notes,
            createdAt: $membership->createdAt?->format('c'),
            isActive: $membership->isActive(),
            isExpired: $membership->isExpired(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->memberId,
            'period_type' => $this->periodType,
            'period_type_label' => $this->periodTypeLabel,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'activated_at' => $this->activatedAt,
            'cancelled_at' => $this->cancelledAt,
            'notes' => $this->notes,
            'created_at' => $this->createdAt,
            'is_active' => $this->isActive,
            'is_expired' => $this->isExpired,
        ];
    }
}
