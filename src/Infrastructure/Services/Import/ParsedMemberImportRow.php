<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services\Import;

use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;

/**
 * A row read into typed values. Days are midnight in the display zone (CalendarDate). A
 * value that could not be read is null and has its message in $errors.
 */
final readonly class ParsedMemberImportRow
{
    /**
     * @param  array<string, list<string>>  $errors  by column code
     */
    public function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?DateTimeImmutable $birthDate,
        public ?string $address,
        public MemberType $memberType,
        public DateTimeImmutable $joinedAt,
        public ?MemberNumber $memberNumber,
        public ?string $memberNumberText,
        public ?string $notes,
        public bool $hasPeriod,
        public ?MembershipPeriodType $periodType,
        public ?DateTimeImmutable $periodStart,
        public ?DateTimeImmutable $periodEnd,
        public bool $hasFee,
        public ?int $feeAmountCents,
        public ?DateTimeImmutable $feeDueDate,
        public ?DateTimeImmutable $feePaidAt,
        public ?PaymentMethod $paymentMethod,
        public ?string $paymentReference,
        public array $errors,
    ) {}

    public function fullName(): ?string
    {
        $name = trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return $name === '' ? null : $name;
    }

    /** The email in lower case, as duplicates are compared. */
    public function emailKey(): ?string
    {
        return $this->email !== null ? mb_strtolower($this->email) : null;
    }

    /**
     * Whether the row has a period and a fee that can be created (checked by the importer
     * only for rows without errors).
     *
     * @phpstan-assert-if-true !null $this->periodType
     * @phpstan-assert-if-true !null $this->periodStart
     * @phpstan-assert-if-true !null $this->periodEnd
     */
    public function createsPeriod(): bool
    {
        return $this->hasPeriod && $this->periodType instanceof MembershipPeriodType && $this->periodStart instanceof DateTimeImmutable && $this->periodEnd instanceof DateTimeImmutable;
    }

    /**
     * @phpstan-assert-if-true !null $this->feeAmountCents
     */
    public function createsFee(): bool
    {
        return $this->hasFee && $this->feeAmountCents !== null;
    }
}
