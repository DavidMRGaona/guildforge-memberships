<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Entities;

use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\ValueObjects\FeeStructureId;
use Modules\Memberships\Domain\ValueObjects\Money;

final class FeeStructure
{
    /**
     * @param  array<string, mixed>|null  $prorationRules
     */
    public function __construct(
        public readonly FeeStructureId $id,
        public MemberType $memberType,
        public MembershipPeriodType $periodType,
        public Money $amount,
        public ?array $prorationRules = null,
        public DateTimeImmutable $validFrom = new DateTimeImmutable(),
        public ?DateTimeImmutable $validUntil = null,
        public ?string $description = null,
        public bool $isDefault = false,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function updateAmount(Money $amount): void
    {
        $this->amount = $amount;
    }

    public function setValidUntil(DateTimeImmutable $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function isCurrentlyValid(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        if ($now < $this->validFrom) {
            return false;
        }

        if ($this->validUntil !== null && $now > $this->validUntil) {
            return false;
        }

        return true;
    }

    public function setAsDefault(): void
    {
        $this->isDefault = true;
    }

    public function unsetAsDefault(): void
    {
        $this->isDefault = false;
    }
}
