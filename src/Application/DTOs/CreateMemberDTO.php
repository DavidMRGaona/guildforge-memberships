<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

final readonly class CreateMemberDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $birthDate = null,
        public ?string $address = null,
        public string $memberType = 'regular',
        public ?string $userId = null,
        public ?string $notes = null,
        public ?string $joinedAt = null,
    ) {
    }

    /**
     * @param  array{first_name: string, last_name: string, email?: string|null, phone?: string|null, birth_date?: string|null, address?: string|null, member_type?: string, user_id?: string|null, notes?: string|null, joined_at?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            birthDate: $data['birth_date'] ?? null,
            address: $data['address'] ?? null,
            memberType: $data['member_type'] ?? 'regular',
            userId: $data['user_id'] ?? null,
            notes: $data['notes'] ?? null,
            joinedAt: $data['joined_at'] ?? null,
        );
    }
}
