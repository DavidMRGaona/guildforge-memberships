<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Enums;

use Modules\Memberships\Domain\Enums\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class PaymentMethodTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = PaymentMethod::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(PaymentMethod::Cash, $cases);
        $this->assertContains(PaymentMethod::BankTransfer, $cases);
        $this->assertContains(PaymentMethod::Card, $cases);
        $this->assertContains(PaymentMethod::Other, $cases);
    }

    public function test_it_has_correct_values(): void
    {
        $this->assertEquals('cash', PaymentMethod::Cash->value);
        $this->assertEquals('bank_transfer', PaymentMethod::BankTransfer->value);
        $this->assertEquals('card', PaymentMethod::Card->value);
        $this->assertEquals('other', PaymentMethod::Other->value);
    }

    public function test_it_returns_label(): void
    {
        // Without a translator (plain PHPUnit) label() returns its translation key
        $this->assertSame('memberships::memberships.enums.payment_method.cash', PaymentMethod::Cash->label());
        $this->assertSame('memberships::memberships.enums.payment_method.bank_transfer', PaymentMethod::BankTransfer->label());
        $this->assertSame('memberships::memberships.enums.payment_method.card', PaymentMethod::Card->label());
        $this->assertSame('memberships::memberships.enums.payment_method.other', PaymentMethod::Other->label());
    }

    public function test_values_returns_string_values(): void
    {
        $values = PaymentMethod::values();

        $this->assertContains('cash', $values);
        $this->assertContains('bank_transfer', $values);
        $this->assertContains('card', $values);
        $this->assertContains('other', $values);
    }

    public function test_icon_returns_appropriate_icons(): void
    {
        $this->assertEquals('heroicon-o-banknotes', PaymentMethod::Cash->icon());
        $this->assertEquals('heroicon-o-building-library', PaymentMethod::BankTransfer->icon());
        $this->assertEquals('heroicon-o-credit-card', PaymentMethod::Card->icon());
        $this->assertEquals('heroicon-o-ellipsis-horizontal', PaymentMethod::Other->icon());
    }
}
