<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Enums;

use Modules\Memberships\Domain\Enums\MemberType;
use PHPUnit\Framework\TestCase;

final class MemberTypeTest extends TestCase
{
    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('regular', MemberType::Regular->value);
        $this->assertEquals('student', MemberType::Student->value);
        $this->assertEquals('senior', MemberType::Senior->value);
        $this->assertEquals('honorary', MemberType::Honorary->value);
        $this->assertEquals('founder', MemberType::Founder->value);
    }

    public function test_honorary_does_not_require_fee(): void
    {
        $this->assertFalse(MemberType::Honorary->requiresFee());
        $this->assertTrue(MemberType::Regular->requiresFee());
        $this->assertTrue(MemberType::Student->requiresFee());
        $this->assertTrue(MemberType::Senior->requiresFee());
        $this->assertTrue(MemberType::Founder->requiresFee());
    }

    public function test_it_returns_label(): void
    {
        // Without a translator (plain PHPUnit) label() returns its translation key
        $this->assertSame('memberships::memberships.enums.member_type.regular', MemberType::Regular->label());
        $this->assertSame('memberships::memberships.enums.member_type.student', MemberType::Student->label());
        $this->assertSame('memberships::memberships.enums.member_type.senior', MemberType::Senior->label());
        $this->assertSame('memberships::memberships.enums.member_type.honorary', MemberType::Honorary->label());
        $this->assertSame('memberships::memberships.enums.member_type.founder', MemberType::Founder->label());
    }

    public function test_values_returns_string_values(): void
    {
        $values = MemberType::values();

        $this->assertContains('regular', $values);
        $this->assertContains('student', $values);
        $this->assertContains('senior', $values);
        $this->assertContains('honorary', $values);
        $this->assertContains('founder', $values);
    }

    public function test_color_returns_appropriate_colors(): void
    {
        $this->assertEquals('primary', MemberType::Regular->color());
        $this->assertEquals('info', MemberType::Student->color());
        $this->assertEquals('warning', MemberType::Senior->color());
        $this->assertEquals('success', MemberType::Honorary->color());
        $this->assertEquals('danger', MemberType::Founder->color());
    }
}
