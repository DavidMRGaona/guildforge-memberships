<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Entities;

use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;

final class Member
{
    public function __construct(
        public readonly MemberId $id,
        public readonly MemberNumber $memberNumber,
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?string $phone = null,
        public ?DateTimeImmutable $birthDate = null,
        public ?string $address = null,
        public MemberType $memberType = MemberType::Regular,
        public MemberStatus $status = MemberStatus::Active,
        public ?string $userId = null,
        public ?string $notes = null,
        public readonly DateTimeImmutable $joinedAt = new DateTimeImmutable(),
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function fullName(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    public function updatePersonalInfo(
        string $firstName,
        string $lastName,
        ?DateTimeImmutable $birthDate = null,
    ): void {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->birthDate = $birthDate;
    }

    public function updateContactInfo(
        ?string $email = null,
        ?string $phone = null,
        ?string $address = null,
    ): void {
        $this->email = $email;
        $this->phone = $phone;
        $this->address = $address;
    }

    public function changeStatus(MemberStatus $status): void
    {
        $this->status = $status;
    }

    public function linkToUser(string $userId): void
    {
        $this->userId = $userId;
    }

    public function unlinkFromUser(): void
    {
        $this->userId = null;
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active;
    }

    public function isLinkedToUser(): bool
    {
        return $this->userId !== null;
    }
}
