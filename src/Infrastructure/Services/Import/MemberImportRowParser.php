<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services\Import;

use BackedEnum;
use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\MemberImportColumn;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

/**
 * Reads the cells of one row (by column) into typed values, collecting a translated message
 * for every value it cannot take. It never looks at the database.
 */
final class MemberImportRowParser
{
    /** The longest text a string column holds. */
    private const int MAX_TEXT_LENGTH = 255;

    private const string ISO_DAY = '/^(\d{4})-(\d{2})-(\d{2})\z/';

    private const string DAY_FIRST = '#^(\d{1,2})/(\d{1,2})/(\d{4})\z#';

    /** Up to the eight whole digits the amount column holds, and at most two decimals. */
    private const string AMOUNT = '/^(\d{1,8})(?:[.,](\d{1,2}))?\z/';

    private readonly DateTimeImmutable $today;

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, string> */
    private array $cells = [];

    public function __construct(
        private readonly MembershipSettingsReader $settings,
    ) {
        $this->today = CalendarDate::today();
    }

    /**
     * @param  array<string, string>  $cells  column code → text
     */
    public function parse(array $cells): ParsedMemberImportRow
    {
        $this->cells = $cells;
        $this->errors = [];

        $firstName = $this->text(MemberImportColumn::FirstName, required: true);
        $lastName = $this->text(MemberImportColumn::LastName, required: true);
        $email = $this->email();
        $phone = $this->text(MemberImportColumn::Phone);
        $birthDate = $this->day(MemberImportColumn::BirthDate, notAfterToday: true);
        $address = $this->text(MemberImportColumn::Address, maxLength: null);
        $memberType = $this->choice(MemberImportColumn::MemberType, MemberType::cases(), 'member_type', 'member_type') ?? MemberType::Regular;
        $joinedAt = $this->day(MemberImportColumn::JoinedAt, notAfterToday: true) ?? $this->today;
        $memberNumberText = $this->text(MemberImportColumn::MemberNumber);
        $memberNumber = $this->memberNumber($memberNumberText);
        $notes = $this->text(MemberImportColumn::Notes, maxLength: null);

        $hasPeriod = $this->anyGiven(MemberImportColumn::periodColumns());
        [$periodType, $periodStart, $periodEnd] = $hasPeriod ? $this->period() : [null, null, null];

        $hasFee = $this->anyGiven(MemberImportColumn::feeColumns());
        $feeAmountCents = null;
        $feeDueDate = null;
        $feePaidAt = null;
        $paymentMethod = null;
        $paymentReference = null;

        if ($hasFee) {
            $feeAmountCents = $this->amount();
            $feeDueDate = $this->day(MemberImportColumn::FeeDueDate) ?? $periodStart;
            $feePaidAt = $this->day(MemberImportColumn::FeePaidAt, notAfterToday: true);
            $paymentMethod = $this->choice(MemberImportColumn::PaymentMethod, PaymentMethod::cases(), 'payment_method', 'payment_method');
            $paymentReference = $this->text(MemberImportColumn::PaymentReference);

            if (! $hasPeriod) {
                $this->fail(MemberImportColumn::FeeAmount, 'fee_needs_period');
            }

            if ($this->raw(MemberImportColumn::FeePaidAt) === null
                && ($this->raw(MemberImportColumn::PaymentMethod) !== null || $this->raw(MemberImportColumn::PaymentReference) !== null)) {
                $this->fail(MemberImportColumn::FeePaidAt, 'paid_at_required');
            }
        }

        return new ParsedMemberImportRow(
            firstName: $firstName,
            lastName: $lastName,
            email: $email,
            phone: $phone,
            birthDate: $birthDate,
            address: $address,
            memberType: $memberType,
            joinedAt: $joinedAt,
            memberNumber: $memberNumber,
            memberNumberText: $memberNumberText,
            notes: $notes,
            hasPeriod: $hasPeriod,
            periodType: $periodType,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            hasFee: $hasFee,
            feeAmountCents: $feeAmountCents,
            feeDueDate: $feeDueDate,
            feePaidAt: $feePaidAt,
            paymentMethod: $paymentMethod,
            paymentReference: $paymentReference,
            errors: $this->errors,
        );
    }

    /**
     * The period's type (the module's default when the row gives none) and its days: those
     * the row gives, the others worked out from the type.
     *
     * @return array{MembershipPeriodType|null, DateTimeImmutable|null, DateTimeImmutable|null}
     */
    private function period(): array
    {
        $typeGiven = $this->raw(MemberImportColumn::PeriodType) !== null;
        $type = $this->choice(MemberImportColumn::PeriodType, MembershipPeriodType::cases(), 'membership_period_type', 'period_type');
        $start = $this->day(MemberImportColumn::PeriodStart);
        $end = $this->day(MemberImportColumn::PeriodEnd);

        $startGiven = $this->raw(MemberImportColumn::PeriodStart) !== null;
        $endGiven = $this->raw(MemberImportColumn::PeriodEnd) !== null;

        if (($typeGiven && ! $type instanceof BackedEnum) || ($startGiven && ! $start instanceof DateTimeImmutable) || ($endGiven && ! $end instanceof DateTimeImmutable)) {
            return [$type, $start, $end];
        }

        $type ??= $this->settings->defaultPeriodType();
        $month = $this->settings->academicStartMonth();

        if (! $start instanceof DateTimeImmutable && ! $end instanceof DateTimeImmutable) {
            $dates = $type->getPeriodDates($this->today, $month);
            [$start, $end] = [$dates['start'], $dates['end']];
        } elseif (! $start instanceof DateTimeImmutable) {
            $start = $type === MembershipPeriodType::Rolling
                ? $end->modify('-1 year +1 day')
                : $type->getPeriodDates($end, $month)['start'];
        } elseif (! $end instanceof DateTimeImmutable) {
            $end = $type->getPeriodDates($start, $month)['end'];

            if ($end < $start) {
                $this->fail(MemberImportColumn::PeriodEnd, 'period_end_required');

                return [$type, $start, null];
            }
        }

        if ($end < $start) {
            $this->fail(MemberImportColumn::PeriodEnd, 'period_end_before_start');

            return [$type, $start, null];
        }

        return [$type, $start, $end];
    }

    private function text(MemberImportColumn $column, bool $required = false, ?int $maxLength = self::MAX_TEXT_LENGTH): ?string
    {
        $text = $this->raw($column);

        if ($text === null) {
            if ($required) {
                $this->fail($column, 'required');
            }

            return null;
        }

        if ($maxLength !== null && mb_strlen($text) > $maxLength) {
            $this->fail($column, 'too_long', ['max' => $maxLength]);

            return null;
        }

        return $text;
    }

    private function email(): ?string
    {
        $email = $this->text(MemberImportColumn::Email);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail(MemberImportColumn::Email, 'email');

            return null;
        }

        return $email;
    }

    private function memberNumber(?string $text): ?MemberNumber
    {
        if ($text === null) {
            return null;
        }

        if (! MemberNumber::isValid($text)) {
            $this->fail(MemberImportColumn::MemberNumber, 'member_number');

            return null;
        }

        return new MemberNumber($text);
    }

    private function day(MemberImportColumn $column, bool $notAfterToday = false): ?DateTimeImmutable
    {
        $text = $this->raw($column);

        if ($text === null) {
            return null;
        }

        if (preg_match(self::ISO_DAY, $text, $parts) === 1) {
            [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        } elseif (preg_match(self::DAY_FIRST, $text, $parts) === 1) {
            [$day, $month, $year] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        } else {
            $this->fail($column, 'date');

            return null;
        }

        if (! checkdate($month, $day, $year)) {
            $this->fail($column, 'date');

            return null;
        }

        $date = CalendarDate::parse(sprintf('%04d-%02d-%02d', $year, $month, $day));

        if ($notAfterToday && $date > $this->today) {
            $this->fail($column, 'future_date');

            return null;
        }

        return $date;
    }

    private function amount(): ?int
    {
        $text = $this->raw(MemberImportColumn::FeeAmount);

        if ($text === null) {
            $this->fail(MemberImportColumn::FeeAmount, 'required');

            return null;
        }

        $text = str_replace(['€', ' ', "\u{00A0}"], '', $text);

        if (preg_match(self::AMOUNT, $text, $parts) !== 1) {
            $this->fail(MemberImportColumn::FeeAmount, 'amount');

            return null;
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '0', 2, '0');
    }

    /**
     * A case of the enum, by its code or its label (current locale or Spanish).
     *
     * @template T of MemberType|MembershipPeriodType|PaymentMethod
     *
     * @param  list<T>  $cases
     * @param  string  $labels  the group of the enum's labels in the translations
     * @return T|null
     */
    private function choice(MemberImportColumn $column, array $cases, string $labels, string $error): ?BackedEnum
    {
        $text = $this->raw($column);

        if ($text === null) {
            return null;
        }

        $wanted = MemberImportText::normalise($text);

        foreach ($cases as $case) {
            $key = 'memberships::memberships.enums.'.$labels.'.'.$case->value;

            foreach ([$case->value, __($key), __($key, [], 'es')] as $name) {
                if (is_string($name) && MemberImportText::normalise($name) === $wanted) {
                    return $case;
                }
            }
        }

        $this->fail($column, $error, ['options' => implode(', ', array_map(
            static fn (MemberType|MembershipPeriodType|PaymentMethod $case): string => $case->label(),
            $cases,
        ))]);

        return null;
    }

    /**
     * @param  list<MemberImportColumn>  $columns
     */
    private function anyGiven(array $columns): bool
    {
        return array_any($columns, fn ($column) => $this->raw($column) !== null);
    }

    /** The trimmed text of a cell, or null when it is empty or missing. */
    private function raw(MemberImportColumn $column): ?string
    {
        $text = trim($this->cells[$column->value] ?? '');

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function fail(MemberImportColumn $column, string $error, array $replace = []): void
    {
        $this->errors[$column->value][] = (string) __('memberships::memberships.import.errors.'.$error, $replace);
    }
}
