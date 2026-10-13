<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use Modules\Memberships\Domain\Enums\MembershipPeriodType;

/**
 * Reads the module settings: values saved in the panel (modules.settings.memberships)
 * win over the module config (config/memberships.php).
 */
final readonly class MembershipSettingsReader
{
    public function defaultPeriodType(): MembershipPeriodType
    {
        return MembershipPeriodType::tryFrom((string) $this->get('default_period_type', MembershipPeriodType::CalendarYear->value))
            ?? MembershipPeriodType::CalendarYear;
    }

    public function academicStartMonth(): int
    {
        $month = (int) $this->get('academic_start_month', 9);

        return $month >= 1 && $month <= 12 ? $month : 9;
    }

    public function roleAssignmentEnabled(): bool
    {
        return (bool) $this->get('enable_role_assignment', false);
    }

    public function memberRoleName(): string
    {
        $roleName = trim((string) $this->get('member_role_name', 'member'));

        return $roleName !== '' ? $roleName : 'member';
    }

    public function expirationWarningDays(): int
    {
        return max(0, (int) $this->get('expiration_warning_days', 30));
    }

    private function get(string $key, mixed $default): mixed
    {
        return config("modules.settings.memberships.{$key}") ?? config("memberships.{$key}", $default);
    }
}
