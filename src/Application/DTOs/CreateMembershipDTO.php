<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

final readonly class CreateMembershipDTO
{
    public function __construct(
        public string $memberId,
        public string $periodType = 'calendar_year',
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $notes = null,
    ) {
    }

    /**
     * @param  array{member_id: string, period_type?: string, start_date?: string|null, end_date?: string|null, notes?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            memberId: $data['member_id'],
            periodType: $data['period_type'] ?? 'calendar_year',
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}
