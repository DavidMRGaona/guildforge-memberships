<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Enums;

enum MemberType: string
{
    case Regular = 'regular';
    case Student = 'student';
    case Senior = 'senior';
    case Honorary = 'honorary';
    case Founder = 'founder';

    /**
     * Check if the member type requires a fee.
     */
    public function requiresFee(): bool
    {
        return $this !== self::Honorary;
    }

    /**
     * Get human-readable label for the type.
     */
    public function label(): string
    {
        $key = match ($this) {
            self::Regular => 'memberships::memberships.enums.member_type.regular',
            self::Student => 'memberships::memberships.enums.member_type.student',
            self::Senior => 'memberships::memberships.enums.member_type.senior',
            self::Honorary => 'memberships::memberships.enums.member_type.honorary',
            self::Founder => 'memberships::memberships.enums.member_type.founder',
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
            self::Regular => 'primary',
            self::Student => 'info',
            self::Senior => 'warning',
            self::Honorary => 'success',
            self::Founder => 'danger',
        };
    }

    /**
     * Get all type values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
