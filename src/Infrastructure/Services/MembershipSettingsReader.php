<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;

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

    /**
     * Member types that hold the association member role without a membership and are
     * left out of expiry warnings and overdue fees. Unknown values are ignored; a saved
     * empty list means no type is permanent.
     *
     * @return list<MemberType>
     */
    public function permanentMemberTypes(): array
    {
        $values = $this->get('permanent_member_types', [MemberType::Honorary->value]);

        if (! is_array($values)) {
            return [];
        }

        $types = [];

        foreach ($values as $value) {
            $type = is_string($value) ? MemberType::tryFrom($value) : null;

            if ($type !== null && ! in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public function expirationWarningDays(): int
    {
        return max(0, (int) $this->get('expiration_warning_days', 30));
    }

    /**
     * Email the member when their membership expires (off until an admin enables it).
     */
    public function expiredEmailEnabled(): bool
    {
        return (bool) $this->get('send_expired_email', false);
    }

    /**
     * Email the member about unpaid overdue fees (off until an admin enables it).
     */
    public function overdueEmailEnabled(): bool
    {
        return (bool) $this->get('send_overdue_email', false);
    }

    private function get(string $key, mixed $default): mixed
    {
        return config("modules.settings.memberships.{$key}") ?? config("memberships.{$key}", $default);
    }
}
