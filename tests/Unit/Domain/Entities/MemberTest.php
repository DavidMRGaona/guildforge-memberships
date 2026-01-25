<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;
use PHPUnit\Framework\TestCase;

final class MemberTest extends TestCase
{
    public function test_it_creates_member_with_required_data(): void
    {
        $id = MemberId::generate();
        $memberNumber = MemberNumber::generate(2026, 1);
        $memberType = MemberType::Regular;
        $status = MemberStatus::Active;
        $joinedAt = new DateTimeImmutable('2026-01-01');

        $member = new Member(
            id: $id,
            memberNumber: $memberNumber,
            firstName: 'John',
            lastName: 'Doe',
            memberType: $memberType,
            status: $status,
            joinedAt: $joinedAt,
        );

        $this->assertInstanceOf(Member::class, $member);
        $this->assertTrue($id->equals($member->id));
        $this->assertTrue($memberNumber->equals($member->memberNumber));
        $this->assertEquals('John', $member->firstName);
        $this->assertEquals('Doe', $member->lastName);
        $this->assertNull($member->email);
        $this->assertNull($member->phone);
        $this->assertNull($member->birthDate);
        $this->assertNull($member->address);
        $this->assertEquals($memberType, $member->memberType);
        $this->assertEquals($status, $member->status);
        $this->assertNull($member->userId);
        $this->assertNull($member->notes);
        $this->assertEquals($joinedAt, $member->joinedAt);
        $this->assertNull($member->createdAt);
    }

    public function test_it_creates_member_with_all_data(): void
    {
        $id = MemberId::generate();
        $memberNumber = MemberNumber::generate(2026, 1);
        $memberType = MemberType::Student;
        $status = MemberStatus::Active;
        $birthDate = new DateTimeImmutable('1995-05-15');
        $joinedAt = new DateTimeImmutable('2026-01-01');
        $createdAt = new DateTimeImmutable('2026-01-01 10:00:00');

        $member = new Member(
            id: $id,
            memberNumber: $memberNumber,
            firstName: 'Jane',
            lastName: 'Smith',
            email: 'jane.smith@example.com',
            phone: '+34 600 123 456',
            birthDate: $birthDate,
            address: 'Calle Mayor 1, Madrid',
            memberType: $memberType,
            status: $status,
            userId: 'user-uuid-123',
            notes: 'Student member',
            joinedAt: $joinedAt,
            createdAt: $createdAt,
        );

        $this->assertInstanceOf(Member::class, $member);
        $this->assertEquals('jane.smith@example.com', $member->email);
        $this->assertEquals('+34 600 123 456', $member->phone);
        $this->assertEquals($birthDate, $member->birthDate);
        $this->assertEquals('Calle Mayor 1, Madrid', $member->address);
        $this->assertEquals('user-uuid-123', $member->userId);
        $this->assertEquals('Student member', $member->notes);
        $this->assertEquals($createdAt, $member->createdAt);
    }

    public function test_it_returns_full_name(): void
    {
        $member = $this->createMember('John', 'Doe');

        $this->assertEquals('John Doe', $member->fullName());
    }

    public function test_it_can_update_personal_info(): void
    {
        $member = $this->createMember('John', 'Doe');
        $newBirthDate = new DateTimeImmutable('1990-03-20');

        $member->updatePersonalInfo(
            firstName: 'Jonathan',
            lastName: 'Doe-Smith',
            birthDate: $newBirthDate,
        );

        $this->assertEquals('Jonathan', $member->firstName);
        $this->assertEquals('Doe-Smith', $member->lastName);
        $this->assertEquals($newBirthDate, $member->birthDate);
    }

    public function test_it_can_update_contact_info(): void
    {
        $member = $this->createMember('John', 'Doe');

        $member->updateContactInfo(
            email: 'john.doe@example.com',
            phone: '+34 600 111 222',
            address: 'Calle Nueva 5, Barcelona',
        );

        $this->assertEquals('john.doe@example.com', $member->email);
        $this->assertEquals('+34 600 111 222', $member->phone);
        $this->assertEquals('Calle Nueva 5, Barcelona', $member->address);
    }

    public function test_it_can_change_status(): void
    {
        $member = $this->createMember('John', 'Doe');

        $member->changeStatus(MemberStatus::Suspended);

        $this->assertEquals(MemberStatus::Suspended, $member->status);
    }

    public function test_it_can_link_to_user(): void
    {
        $member = $this->createMember('John', 'Doe');

        $member->linkToUser('user-uuid-456');

        $this->assertEquals('user-uuid-456', $member->userId);
    }

    public function test_it_can_unlink_from_user(): void
    {
        $member = $this->createMember('John', 'Doe');
        $member->linkToUser('user-uuid-456');

        $member->unlinkFromUser();

        $this->assertNull($member->userId);
    }

    public function test_it_checks_if_is_active(): void
    {
        $activeMember = $this->createMember('John', 'Doe', MemberStatus::Active);
        $inactiveMember = $this->createMember('Jane', 'Smith', MemberStatus::Inactive);

        $this->assertTrue($activeMember->isActive());
        $this->assertFalse($inactiveMember->isActive());
    }

    public function test_it_checks_if_is_linked_to_user(): void
    {
        $linkedMember = $this->createMember('John', 'Doe');
        $linkedMember->linkToUser('user-uuid-789');

        $unlinkedMember = $this->createMember('Jane', 'Smith');

        $this->assertTrue($linkedMember->isLinkedToUser());
        $this->assertFalse($unlinkedMember->isLinkedToUser());
    }

    private function createMember(
        string $firstName,
        string $lastName,
        MemberStatus $status = MemberStatus::Active,
    ): Member {
        return new Member(
            id: MemberId::generate(),
            memberNumber: MemberNumber::generate(2026, 1),
            firstName: $firstName,
            lastName: $lastName,
            memberType: MemberType::Regular,
            status: $status,
            joinedAt: new DateTimeImmutable(),
        );
    }
}
