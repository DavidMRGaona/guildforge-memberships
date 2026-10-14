<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use App\Domain\Enums\UserRole;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;

/**
 * Reads the module settings: values saved in the panel (modules.settings.memberships)
 * win over the module config (config/memberships.php).
 */
final readonly class MembershipSettingsReader
{
    /**
     * Roles the module must never grant or revoke: granting them to whoever has an
     * active membership would hand out the panel.
     */
    public const array PRIVILEGED_ROLES = [UserRole::Admin->value, UserRole::Editor->value];

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
        $roleName = trim((string) $this->get('member_role_name', UserRole::Member->value));

        if ($roleName === '' || in_array(strtolower($roleName), self::PRIVILEGED_ROLES, true)) {
            return UserRole::Member->value;
        }

        return $roleName;
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
