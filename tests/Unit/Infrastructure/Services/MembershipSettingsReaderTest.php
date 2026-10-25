<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Infrastructure\Services;

use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
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
        $this->reader = new MembershipSettingsReader;
    }

    public function test_defaults_apply_when_nothing_is_configured(): void
    {
        $this->assertSame(MembershipPeriodType::CalendarYear, $this->reader->defaultPeriodType());
        $this->assertSame(9, $this->reader->academicStartMonth());
        $this->assertFalse($this->reader->roleAssignmentEnabled());
        $this->assertSame([MemberType::Honorary], $this->reader->permanentMemberTypes());
        $this->assertSame(30, $this->reader->expirationWarningDays());
    }

    public function test_module_config_is_the_fallback(): void
    {
        config()->set('memberships', [
            'default_period_type' => 'academic_year',
            'academic_start_month' => 10,
            'enable_role_assignment' => true,
            'permanent_member_types' => ['founder'],
            'expiration_warning_days' => 15,
        ]);

        $this->assertSame(MembershipPeriodType::AcademicYear, $this->reader->defaultPeriodType());
        $this->assertSame(10, $this->reader->academicStartMonth());
        $this->assertTrue($this->reader->roleAssignmentEnabled());
        $this->assertSame([MemberType::Founder], $this->reader->permanentMemberTypes());
        $this->assertSame(15, $this->reader->expirationWarningDays());
    }

    public function test_panel_values_win_over_the_module_config(): void
    {
        config()->set('memberships', [
            'default_period_type' => 'academic_year',
            'academic_start_month' => 10,
            'enable_role_assignment' => true,
            'permanent_member_types' => ['founder'],
            'expiration_warning_days' => 15,
        ]);
        config()->set('modules.settings.memberships', [
            'default_period_type' => 'rolling',
            'academic_start_month' => '2',
            'enable_role_assignment' => false,
            'permanent_member_types' => ['honorary', 'senior'],
            'expiration_warning_days' => '7',
        ]);

        $this->assertSame(MembershipPeriodType::Rolling, $this->reader->defaultPeriodType());
        $this->assertSame(2, $this->reader->academicStartMonth());
        $this->assertFalse($this->reader->roleAssignmentEnabled());
        $this->assertSame([MemberType::Honorary, MemberType::Senior], $this->reader->permanentMemberTypes());
        $this->assertSame(7, $this->reader->expirationWarningDays());
    }

    public function test_an_unknown_period_type_falls_back_to_the_calendar_year(): void
    {
        config()->set('modules.settings.memberships.default_period_type', 'weekly');

        $this->assertSame(MembershipPeriodType::CalendarYear, $this->reader->defaultPeriodType());
    }

    public function test_an_empty_list_of_permanent_types_saved_in_the_panel_is_kept(): void
    {
        config()->set('memberships.permanent_member_types', ['honorary']);
        config()->set('modules.settings.memberships.permanent_member_types', []);

        $this->assertSame([], $this->reader->permanentMemberTypes());
    }

    public function test_unknown_permanent_types_are_ignored(): void
    {
        config()->set('modules.settings.memberships.permanent_member_types', ['honorary', 'board', 42, null, 'founder', 'honorary']);

        $this->assertSame([MemberType::Honorary, MemberType::Founder], $this->reader->permanentMemberTypes());
    }

    public function test_a_permanent_types_value_that_is_not_a_list_means_none(): void
    {
        config()->set('modules.settings.memberships.permanent_member_types', 'honorary');

        $this->assertSame([], $this->reader->permanentMemberTypes());
    }

    public function test_the_shipped_defaults_make_honorary_members_permanent_and_carry_no_role_name(): void
    {
        foreach (['settings.php', 'memberships.php'] as $file) {
            /** @var array<string, mixed> $defaults */
            $defaults = require dirname(__DIR__, 4).'/config/'.$file;

            $this->assertSame(['honorary'], $defaults['permanent_member_types'] ?? null, $file);
            // The association member role is a core site setting
            $this->assertArrayNotHasKey('member_role_name', $defaults, $file);
        }
    }
}
