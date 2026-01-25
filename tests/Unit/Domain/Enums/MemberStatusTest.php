<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Enums;

use Modules\Memberships\Domain\Enums\MemberStatus;
use PHPUnit\Framework\TestCase;

final class MemberStatusTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = MemberStatus::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(MemberStatus::Active, $cases);
        $this->assertContains(MemberStatus::Inactive, $cases);
        $this->assertContains(MemberStatus::Suspended, $cases);
        $this->assertContains(MemberStatus::Expelled, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('active', MemberStatus::Active->value);
        $this->assertEquals('inactive', MemberStatus::Inactive->value);
        $this->assertEquals('suspended', MemberStatus::Suspended->value);
        $this->assertEquals('expelled', MemberStatus::Expelled->value);
    }

    public function test_active_status_is_active(): void
    {
        $this->assertTrue(MemberStatus::Active->isActive());
        $this->assertFalse(MemberStatus::Inactive->isActive());
        $this->assertFalse(MemberStatus::Suspended->isActive());
        $this->assertFalse(MemberStatus::Expelled->isActive());
    }

    public function test_inactive_and_suspended_can_be_activated(): void
    {
        $this->assertTrue(MemberStatus::Inactive->canBeActivated());
        $this->assertTrue(MemberStatus::Suspended->canBeActivated());
        $this->assertFalse(MemberStatus::Active->canBeActivated());
        $this->assertFalse(MemberStatus::Expelled->canBeActivated());
    }

    public function test_it_returns_label(): void
    {
        // This test will fail until the label() method is implemented
        // The label() method should return translated strings from:
        // memberships::memberships.enums.member_status.active
        // memberships::memberships.enums.member_status.inactive
        // memberships::memberships.enums.member_status.suspended
        // memberships::memberships.enums.member_status.expelled

        $this->assertIsString(MemberStatus::Active->label());
        $this->assertIsString(MemberStatus::Inactive->label());
        $this->assertIsString(MemberStatus::Suspended->label());
        $this->assertIsString(MemberStatus::Expelled->label());
    }

    public function test_values_returns_string_values(): void
    {
        $values = MemberStatus::values();

        $this->assertIsArray($values);
        $this->assertContains('active', $values);
        $this->assertContains('inactive', $values);
        $this->assertContains('suspended', $values);
        $this->assertContains('expelled', $values);
    }

    public function test_color_returns_appropriate_colors(): void
    {
        $this->assertEquals('success', MemberStatus::Active->color());
        $this->assertEquals('gray', MemberStatus::Inactive->color());
        $this->assertEquals('warning', MemberStatus::Suspended->color());
        $this->assertEquals('danger', MemberStatus::Expelled->color());
    }
}
