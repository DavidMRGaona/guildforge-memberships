<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Enums;

use Modules\Memberships\Domain\Enums\MembershipStatus;
use PHPUnit\Framework\TestCase;

final class MembershipStatusTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = MembershipStatus::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(MembershipStatus::Pending, $cases);
        $this->assertContains(MembershipStatus::Active, $cases);
        $this->assertContains(MembershipStatus::Expired, $cases);
        $this->assertContains(MembershipStatus::Cancelled, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('pending', MembershipStatus::Pending->value);
        $this->assertEquals('active', MembershipStatus::Active->value);
        $this->assertEquals('expired', MembershipStatus::Expired->value);
        $this->assertEquals('cancelled', MembershipStatus::Cancelled->value);
    }

    public function test_active_status_is_active(): void
    {
        $this->assertTrue(MembershipStatus::Active->isActive());
        $this->assertFalse(MembershipStatus::Pending->isActive());
        $this->assertFalse(MembershipStatus::Expired->isActive());
        $this->assertFalse(MembershipStatus::Cancelled->isActive());
    }

    public function test_pending_can_transition_to_active(): void
    {
        $this->assertTrue(MembershipStatus::Pending->canTransitionTo(MembershipStatus::Active));
        $this->assertTrue(MembershipStatus::Pending->canTransitionTo(MembershipStatus::Cancelled));
        $this->assertFalse(MembershipStatus::Pending->canTransitionTo(MembershipStatus::Expired));
        $this->assertFalse(MembershipStatus::Pending->canTransitionTo(MembershipStatus::Pending));
    }

    public function test_active_can_transition_to_expired_or_cancelled(): void
    {
        $this->assertTrue(MembershipStatus::Active->canTransitionTo(MembershipStatus::Expired));
        $this->assertTrue(MembershipStatus::Active->canTransitionTo(MembershipStatus::Cancelled));
        $this->assertFalse(MembershipStatus::Active->canTransitionTo(MembershipStatus::Pending));
        $this->assertFalse(MembershipStatus::Active->canTransitionTo(MembershipStatus::Active));
    }

    public function test_expired_can_transition_to_active(): void
    {
        $this->assertTrue(MembershipStatus::Expired->canTransitionTo(MembershipStatus::Active));
        $this->assertFalse(MembershipStatus::Expired->canTransitionTo(MembershipStatus::Pending));
        $this->assertFalse(MembershipStatus::Expired->canTransitionTo(MembershipStatus::Cancelled));
        $this->assertFalse(MembershipStatus::Expired->canTransitionTo(MembershipStatus::Expired));
    }

    public function test_cancelled_cannot_transition(): void
    {
        $this->assertFalse(MembershipStatus::Cancelled->canTransitionTo(MembershipStatus::Active));
        $this->assertFalse(MembershipStatus::Cancelled->canTransitionTo(MembershipStatus::Pending));
        $this->assertFalse(MembershipStatus::Cancelled->canTransitionTo(MembershipStatus::Expired));
        $this->assertFalse(MembershipStatus::Cancelled->canTransitionTo(MembershipStatus::Cancelled));
    }

    public function test_it_returns_label(): void
    {
        // This test will fail until the label() method is implemented
        // The label() method should return translated strings from:
        // memberships::memberships.enums.membership_status.pending
        // memberships::memberships.enums.membership_status.active
        // memberships::memberships.enums.membership_status.expired
        // memberships::memberships.enums.membership_status.cancelled

        $this->assertIsString(MembershipStatus::Pending->label());
        $this->assertIsString(MembershipStatus::Active->label());
        $this->assertIsString(MembershipStatus::Expired->label());
        $this->assertIsString(MembershipStatus::Cancelled->label());
    }

    public function test_values_returns_string_values(): void
    {
        $values = MembershipStatus::values();

        $this->assertIsArray($values);
        $this->assertContains('pending', $values);
        $this->assertContains('active', $values);
        $this->assertContains('expired', $values);
        $this->assertContains('cancelled', $values);
    }

    public function test_color_returns_appropriate_colors(): void
    {
        $this->assertEquals('warning', MembershipStatus::Pending->color());
        $this->assertEquals('success', MembershipStatus::Active->color());
        $this->assertEquals('gray', MembershipStatus::Expired->color());
        $this->assertEquals('danger', MembershipStatus::Cancelled->color());
    }
}
