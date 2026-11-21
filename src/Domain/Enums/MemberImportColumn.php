<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Enums;

/**
 * The columns an import reads: the member, their current period and its fee. Headers are
 * the translated labels (or these codes), read without case, accents or extra spaces.
 */
enum MemberImportColumn: string
{
    case FirstName = 'first_name';
    case LastName = 'last_name';
    case Email = 'email';
    case Phone = 'phone';
    case BirthDate = 'birth_date';
    case Address = 'address';
    case MemberType = 'member_type';
    case JoinedAt = 'joined_at';
    case MemberNumber = 'member_number';
    case Notes = 'notes';
    case PeriodType = 'period_type';
    case PeriodStart = 'period_start';
    case PeriodEnd = 'period_end';
    case FeeAmount = 'fee_amount';
    case FeeDueDate = 'fee_due_date';
    case FeePaidAt = 'fee_paid_at';
    case PaymentMethod = 'payment_method';
    case PaymentReference = 'payment_reference';

    public function labelKey(): string
    {
        return 'memberships::memberships.import.columns.'.$this->value;
    }

    /**
     * The header of the column in the given locale (the current one by default).
     */
    public function label(?string $locale = null): string
    {
        if (! function_exists('app') || ! app()->bound('translator')) {
            return $this->labelKey();
        }

        return (string) __($this->labelKey(), [], $locale);
    }

    /**
     * @return list<self>
     */
    public static function periodColumns(): array
    {
        return [self::PeriodType, self::PeriodStart, self::PeriodEnd];
    }

    /**
     * @return list<self>
     */
    public static function feeColumns(): array
    {
        return [self::FeeAmount, self::FeeDueDate, self::FeePaidAt, self::PaymentMethod, self::PaymentReference];
    }
}
