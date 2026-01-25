<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Other = 'other';

    /**
     * Get human-readable label for the payment method.
     */
    public function label(): string
    {
        $key = match ($this) {
            self::Cash => 'memberships::memberships.enums.payment_method.cash',
            self::BankTransfer => 'memberships::memberships.enums.payment_method.bank_transfer',
            self::Card => 'memberships::memberships.enums.payment_method.card',
            self::Other => 'memberships::memberships.enums.payment_method.other',
        };

        if (! function_exists('app') || ! app()->bound('translator')) {
            return $key;
        }

        return __($key);
    }

    /**
     * Get icon for Filament UI.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'heroicon-o-banknotes',
            self::BankTransfer => 'heroicon-o-building-library',
            self::Card => 'heroicon-o-credit-card',
            self::Other => 'heroicon-o-ellipsis-horizontal',
        };
    }

    /**
     * Get all payment method values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
