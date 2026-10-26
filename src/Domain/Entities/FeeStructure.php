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
        public DateTimeImmutable $validFrom = new DateTimeImmutable,
        public ?DateTimeImmutable $validUntil = null,
        public ?string $description = null,
        public bool $isDefault = false,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public function updateAmount(Money $amount): void
    {
        $this->amount = $amount;
    }

    public function setValidUntil(DateTimeImmutable $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function updateDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @param  array<string, mixed>|null  $prorationRules
     */
    public function updateProrationRules(?array $prorationRules): void
    {
        $this->prorationRules = $prorationRules;
    }

    /**
     * The validity is made of calendar days: the instant is read in the zone the dates carry
     * (the display zone), so the last day lasts until its midnight there.
     */
    public function isCurrentlyValid(DateTimeImmutable $now): bool
    {
        $day = $now->setTimezone($this->validFrom->getTimezone())->format('Y-m-d');

        if ($day < $this->validFrom->format('Y-m-d')) {
            return false;
        }

        return ! $this->validUntil instanceof DateTimeImmutable
            || $day <= $this->validUntil->format('Y-m-d');
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
