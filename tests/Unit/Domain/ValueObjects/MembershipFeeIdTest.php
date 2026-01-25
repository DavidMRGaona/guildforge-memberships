<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\Memberships\Domain\ValueObjects\MembershipFeeId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class MembershipFeeIdTest extends TestCase
{
    public function test_it_generates_valid_uuid(): void
    {
        $id = MembershipFeeId::generate();

        $this->assertInstanceOf(MembershipFeeId::class, $id);
        $this->assertTrue(Uuid::isValid($id->value()));
    }

    public function test_it_creates_from_valid_uuid_string(): void
    {
        $uuid = Uuid::uuid4()->toString();

        $id = MembershipFeeId::fromString($uuid);

        $this->assertInstanceOf(MembershipFeeId::class, $id);
        $this->assertEquals($uuid, $id->value());
    }

    public function test_it_throws_exception_with_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID: not-a-uuid');

        MembershipFeeId::fromString('not-a-uuid');
    }

    public function test_it_compares_equality(): void
    {
        $uuid = Uuid::uuid4()->toString();
        $id1 = MembershipFeeId::fromString($uuid);
        $id2 = MembershipFeeId::fromString($uuid);
        $id3 = MembershipFeeId::generate();

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function test_it_converts_to_string(): void
    {
        $uuid = Uuid::uuid4()->toString();
        $id = MembershipFeeId::fromString($uuid);

        $this->assertEquals($uuid, (string) $id);
        $this->assertEquals($uuid, $id->__toString());
    }

    public function test_it_returns_value(): void
    {
        $uuid = Uuid::uuid4()->toString();
        $id = MembershipFeeId::fromString($uuid);

        $this->assertEquals($uuid, $id->value());
    }
}
