<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Enums;

enum MembershipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Check if the membership status is active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if transition to another status is allowed.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::Pending => in_array($target, [self::Active, self::Cancelled], true),
            self::Active => in_array($target, [self::Expired, self::Cancelled], true),
            self::Expired => $target === self::Active,
            self::Cancelled => false,
        };
    }

    /**
     * Get human-readable label for the status.
     */
    public function label(): string
    {
        $key = match ($this) {
            self::Pending => 'memberships::memberships.enums.membership_status.pending',
            self::Active => 'memberships::memberships.enums.membership_status.active',
            self::Expired => 'memberships::memberships.enums.membership_status.expired',
            self::Cancelled => 'memberships::memberships.enums.membership_status.cancelled',
        };

        if (! function_exists('app') || ! app()->bound('translator')) {
            return $key;
        }

        return __($key);
    }

    /**
     * Get badge color for Filament UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Expired => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Get all status values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
