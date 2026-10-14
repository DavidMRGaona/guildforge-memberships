<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Infrastructure\Services;

use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;
use Tests\TestCase;

final class MembershipSettingsReaderTest extends TestCase
{
    private MembershipSettingsReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.memberships', []);
        config()->set('memberships', []);
        $this->reader = new MembershipSettingsReader();
    }

    public function test_defaults_apply_when_nothing_is_configured(): void
    {
        $this->assertSame(MembershipPeriodType::CalendarYear, $this->reader->defaultPeriodType());
        $this->assertSame(9, $this->reader->academicStartMonth());
        $this->assertFalse($this->reader->roleAssignmentEnabled());
        $this->assertSame('member', $this->reader->memberRoleName());
        $this->assertSame(30, $this->reader->expirationWarningDays());
    }

    public function test_module_config_is_the_fallback(): void
    {
        config()->set('memberships', [
            'default_period_type' => 'academic_year',
            'academic_start_month' => 10,
            'enable_role_assignment' => true,
            'member_role_name' => 'socio',
            'expiration_warning_days' => 15,
        ]);

        $this->assertSame(MembershipPeriodType::AcademicYear, $this->reader->defaultPeriodType());
        $this->assertSame(10, $this->reader->academicStartMonth());
        $this->assertTrue($this->reader->roleAssignmentEnabled());
        $this->assertSame('socio', $this->reader->memberRoleName());
        $this->assertSame(15, $this->reader->expirationWarningDays());
    }

    public function test_panel_values_win_over_the_module_config(): void
    {
        config()->set('memberships', [
            'default_period_type' => 'academic_year',
            'academic_start_month' => 10,
            'enable_role_assignment' => true,
            'member_role_name' => 'socio',
            'expiration_warning_days' => 15,
        ]);
        config()->set('modules.settings.memberships', [
            'default_period_type' => 'rolling',
            'academic_start_month' => '2',
            'enable_role_assignment' => false,
            'member_role_name' => 'member',
            'expiration_warning_days' => '7',
        ]);

        $this->assertSame(MembershipPeriodType::Rolling, $this->reader->defaultPeriodType());
        $this->assertSame(2, $this->reader->academicStartMonth());
        $this->assertFalse($this->reader->roleAssignmentEnabled());
        $this->assertSame('member', $this->reader->memberRoleName());
        $this->assertSame(7, $this->reader->expirationWarningDays());
    }

    public function test_an_unknown_period_type_falls_back_to_the_calendar_year(): void
    {
        config()->set('modules.settings.memberships.default_period_type', 'weekly');

        $this->assertSame(MembershipPeriodType::CalendarYear, $this->reader->defaultPeriodType());
    }

    public function test_an_empty_role_name_falls_back_to_member(): void
    {
        config()->set('modules.settings.memberships.member_role_name', '');

        $this->assertSame('member', $this->reader->memberRoleName());
    }

    public function test_an_administration_role_falls_back_to_member(): void
    {
        foreach (['admin', 'editor', ' Admin ', 'EDITOR'] as $roleName) {
            config()->set('modules.settings.memberships.member_role_name', $roleName);

            $this->assertSame('member', $this->reader->memberRoleName(), $roleName);
        }
    }

    public function test_an_administration_role_in_the_module_config_falls_back_to_member(): void
    {
        config()->set('memberships.member_role_name', 'admin');

        $this->assertSame('member', $this->reader->memberRoleName());
    }
}
