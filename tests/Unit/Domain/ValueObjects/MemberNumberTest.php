<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\ValueObjects;

use Modules\Memberships\Domain\Exceptions\InvalidMemberNumberException;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;
use PHPUnit\Framework\TestCase;

final class MemberNumberTest extends TestCase
{
    public function test_it_creates_with_valid_format(): void
    {
        $memberNumber = new MemberNumber('2024-0001');

        $this->assertEquals('2024-0001', (string) $memberNumber);
    }

    public function test_it_throws_exception_with_invalid_format_missing_dash(): void
    {
        $this->expectException(InvalidMemberNumberException::class);
        $this->expectExceptionMessage('Invalid member number format');

        new MemberNumber('20240001');
    }

    public function test_it_throws_exception_with_invalid_format_wrong_year_length(): void
    {
        $this->expectException(InvalidMemberNumberException::class);
        $this->expectExceptionMessage('Invalid member number format');

        new MemberNumber('24-0001');
    }

    public function test_it_throws_exception_with_invalid_format_wrong_sequence_length(): void
    {
        $this->expectException(InvalidMemberNumberException::class);
        $this->expectExceptionMessage('Invalid member number format');

        new MemberNumber('2024-001');
    }

    public function test_it_throws_exception_with_empty_value(): void
    {
        $this->expectException(InvalidMemberNumberException::class);
        $this->expectExceptionMessage('Invalid member number format');

        new MemberNumber('');
    }

    public function test_it_converts_to_string(): void
    {
        $memberNumber = new MemberNumber('2024-0042');

        $this->assertEquals('2024-0042', $memberNumber->__toString());
    }

    public function test_it_compares_equality(): void
    {
        $number1 = new MemberNumber('2024-0001');
        $number2 = new MemberNumber('2024-0001');
        $number3 = new MemberNumber('2024-0002');

        $this->assertTrue($number1->equals($number2));
        $this->assertFalse($number1->equals($number3));
    }

    public function test_it_returns_year(): void
    {
        $memberNumber = new MemberNumber('2024-0001');

        $this->assertEquals(2024, $memberNumber->year());
    }

    public function test_it_returns_sequence(): void
    {
        $memberNumber = new MemberNumber('2024-0123');

        $this->assertEquals(123, $memberNumber->sequence());
    }

    public function test_it_generates_from_year_and_sequence(): void
    {
        $memberNumber = MemberNumber::generate(2024, 1);

        $this->assertEquals('2024-0001', (string) $memberNumber);
    }

    public function test_it_generates_with_large_sequence(): void
    {
        $memberNumber = MemberNumber::generate(2024, 9999);

        $this->assertEquals('2024-9999', (string) $memberNumber);
    }

    public function test_it_validates_valid_format(): void
    {
        $this->assertTrue(MemberNumber::isValid('2024-0001'));
        $this->assertTrue(MemberNumber::isValid('2025-9999'));
        $this->assertTrue(MemberNumber::isValid('1999-0100'));
    }

    public function test_it_validates_invalid_format(): void
    {
        $this->assertFalse(MemberNumber::isValid('2024-001'));
        $this->assertFalse(MemberNumber::isValid('24-0001'));
        $this->assertFalse(MemberNumber::isValid('20240001'));
        $this->assertFalse(MemberNumber::isValid(''));
        $this->assertFalse(MemberNumber::isValid('invalid'));
    }

    public function test_it_pads_sequence_correctly(): void
    {
        $number1 = MemberNumber::generate(2024, 1);
        $number2 = MemberNumber::generate(2024, 10);
        $number3 = MemberNumber::generate(2024, 100);
        $number4 = MemberNumber::generate(2024, 1000);

        $this->assertEquals('2024-0001', (string) $number1);
        $this->assertEquals('2024-0010', (string) $number2);
        $this->assertEquals('2024-0100', (string) $number3);
        $this->assertEquals('2024-1000', (string) $number4);
    }
}
