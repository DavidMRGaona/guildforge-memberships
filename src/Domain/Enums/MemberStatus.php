<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Enums;

enum MemberStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Expelled = 'expelled';

    /**
     * Check if the member status is active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if the member can be activated.
     */
    public function canBeActivated(): bool
    {
        return $this === self::Inactive || $this === self::Suspended;
    }

    /**
     * Get human-readable label for the status.
     */
    public function label(): string
    {
        $key = match ($this) {
            self::Active => 'memberships::memberships.enums.member_status.active',
            self::Inactive => 'memberships::memberships.enums.member_status.inactive',
            self::Suspended => 'memberships::memberships.enums.member_status.suspended',
            self::Expelled => 'memberships::memberships.enums.member_status.expelled',
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
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Suspended => 'warning',
            self::Expelled => 'danger',
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
