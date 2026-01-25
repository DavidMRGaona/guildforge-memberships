<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

final readonly class UpdateMemberDTO
{
    public function __construct(
        public string $id,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $birthDate = null,
        public ?string $address = null,
        public ?string $memberType = null,
        public ?string $status = null,
        public ?string $userId = null,
        public ?string $notes = null,
    ) {
    }

    /**
     * @param  array{id: string, first_name?: string|null, last_name?: string|null, email?: string|null, phone?: string|null, birth_date?: string|null, address?: string|null, member_type?: string|null, status?: string|null, user_id?: string|null, notes?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            birthDate: $data['birth_date'] ?? null,
            address: $data['address'] ?? null,
            memberType: $data['member_type'] ?? null,
            status: $data['status'] ?? null,
            userId: $data['user_id'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}
