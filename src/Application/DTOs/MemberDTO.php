<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Entities\Membership;

final readonly class MemberDTO
{
    public function __construct(
        public string $id,
        public string $memberNumber,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public ?string $email,
        public ?string $phone,
        public ?string $birthDate,
        public ?string $address,
        public string $memberType,
        public string $memberTypeLabel,
        public string $status,
        public string $statusLabel,
        public ?string $userId,
        public ?string $notes,
        public string $joinedAt,
        public ?string $createdAt,
        public ?MembershipDTO $activeMembership,
    ) {
    }

    public static function fromEntity(Member $member, ?Membership $activeMembership = null): self
    {
        return new self(
            id: $member->id->value,
            memberNumber: $member->memberNumber->value,
            firstName: $member->firstName,
            lastName: $member->lastName,
            fullName: $member->fullName(),
            email: $member->email,
            phone: $member->phone,
            birthDate: $member->birthDate?->format('Y-m-d'),
            address: $member->address,
            memberType: $member->memberType->value,
            memberTypeLabel: $member->memberType->label(),
            status: $member->status->value,
            statusLabel: $member->status->label(),
            userId: $member->userId,
            notes: $member->notes,
            joinedAt: $member->joinedAt->format('c'),
            createdAt: $member->createdAt?->format('c'),
            activeMembership: $activeMembership !== null
                ? MembershipDTO::fromEntity($activeMembership)
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->memberNumber,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'birth_date' => $this->birthDate,
            'address' => $this->address,
            'member_type' => $this->memberType,
            'member_type_label' => $this->memberTypeLabel,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'user_id' => $this->userId,
            'notes' => $this->notes,
            'joined_at' => $this->joinedAt,
            'created_at' => $this->createdAt,
            'active_membership' => $this->activeMembership?->toArray(),
        ];
    }
}
