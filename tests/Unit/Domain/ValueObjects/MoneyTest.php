<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\Memberships\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_creates_with_amount_and_currency(): void
    {
        $money = new Money(1000, 'EUR');

        $this->assertEquals(1000, $money->amount());
        $this->assertEquals('EUR', $money->currency());
    }

    public function test_it_returns_amount_as_integer(): void
    {
        $money = new Money(1050, 'EUR');

        $this->assertIsInt($money->amount());
        $this->assertEquals(1050, $money->amount());
    }

    public function test_it_returns_currency(): void
    {
        $money = new Money(1000, 'USD');

        $this->assertEquals('USD', $money->currency());
    }

    public function test_it_compares_equality_with_same_amount_and_currency(): void
    {
        $money1 = new Money(1000, 'EUR');
        $money2 = new Money(1000, 'EUR');

        $this->assertTrue($money1->equals($money2));
    }

    public function test_it_compares_inequality_with_different_amounts(): void
    {
        $money1 = new Money(1000, 'EUR');
        $money2 = new Money(2000, 'EUR');

        $this->assertFalse($money1->equals($money2));
    }

    public function test_it_compares_inequality_with_different_currencies(): void
    {
        $money1 = new Money(1000, 'EUR');
        $money2 = new Money(1000, 'USD');

        $this->assertFalse($money1->equals($money2));
    }

    public function test_it_adds_money_with_same_currency(): void
    {
        $money1 = new Money(1000, 'EUR');
        $money2 = new Money(500, 'EUR');

        $result = $money1->add($money2);

        $this->assertEquals(1500, $result->amount());
        $this->assertEquals('EUR', $result->currency());
        $this->assertNotSame($money1, $result);
    }

    public function test_it_throws_exception_when_adding_different_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add money with different currencies: EUR and USD');

        $money1 = new Money(1000, 'EUR');
        $money2 = new Money(500, 'USD');

        $money1->add($money2);
    }

    public function test_it_subtracts_money_with_same_currency(): void
    {
        $money1 = new Money(1000, 'EUR');
        $money2 = new Money(300, 'EUR');

        $result = $money1->subtract($money2);

        $this->assertEquals(700, $result->amount());
        $this->assertEquals('EUR', $result->currency());
        $this->assertNotSame($money1, $result);
    }

    public function test_it_throws_exception_when_subtracting_different_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot subtract money with different currencies: EUR and USD');

        $money1 = new Money(1000, 'EUR');
        $money2 = new Money(500, 'USD');

        $money1->subtract($money2);
    }

    public function test_it_multiplies_by_factor(): void
    {
        $money = new Money(1000, 'EUR');

        $result = $money->multiply(2);

        $this->assertEquals(2000, $result->amount());
        $this->assertEquals('EUR', $result->currency());
        $this->assertNotSame($money, $result);
    }

    public function test_it_multiplies_by_decimal_factor(): void
    {
        $money = new Money(1000, 'EUR');

        $result = $money->multiply(1.5);

        $this->assertEquals(1500, $result->amount());
        $this->assertEquals('EUR', $result->currency());
    }

    public function test_it_converts_to_string(): void
    {
        $money = new Money(1050, 'EUR');

        $this->assertEquals('10.50 EUR', (string) $money);
        $this->assertEquals('10.50 EUR', $money->__toString());
    }

    public function test_it_formats_whole_amounts(): void
    {
        $money = new Money(1000, 'EUR');

        $this->assertEquals('10.00 EUR', (string) $money);
    }

    public function test_it_formats_zero_amount(): void
    {
        $money = new Money(0, 'EUR');

        $this->assertEquals('0.00 EUR', (string) $money);
    }

    public function test_it_creates_from_cents(): void
    {
        $money = Money::fromCents(1050, 'EUR');

        $this->assertEquals(1050, $money->amount());
        $this->assertEquals('EUR', $money->currency());
    }

    public function test_it_converts_to_cents(): void
    {
        $money = new Money(1050, 'EUR');

        $this->assertEquals(1050, $money->toCents());
    }

    public function test_it_is_immutable(): void
    {
        $original = new Money(1000, 'EUR');
        $added = $original->add(new Money(500, 'EUR'));
        $subtracted = $original->subtract(new Money(200, 'EUR'));
        $multiplied = $original->multiply(2);

        $this->assertEquals(1000, $original->amount());
        $this->assertEquals(1500, $added->amount());
        $this->assertEquals(800, $subtracted->amount());
        $this->assertEquals(2000, $multiplied->amount());
    }
}
