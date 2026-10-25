<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_it_can_change_member_type(): void
    {
        $member = $this->createMember('John', 'Doe');

        $member->changeMemberType(MemberType::Student);

        $this->assertEquals(MemberType::Student, $member->memberType);
    }

    public function test_it_can_update_notes(): void
    {
        $member = $this->createMember('John', 'Doe');

        $member->updateNotes('Prefers to be contacted by email');

        $this->assertEquals('Prefers to be contacted by email', $member->notes);
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

    public function test_an_active_member_with_an_active_membership_holds_the_association_role(): void
    {
        $member = $this->createMember('John', 'Doe');

        $this->assertTrue($member->holdsAssociationRole(hasActiveMembership: true, permanentTypes: [MemberType::Honorary]));
    }

    public function test_an_active_member_without_a_membership_does_not_hold_the_association_role(): void
    {
        $member = $this->createMember('John', 'Doe');

        $this->assertFalse($member->holdsAssociationRole(hasActiveMembership: false, permanentTypes: [MemberType::Honorary]));
    }

    public function test_an_active_member_of_a_permanent_type_holds_the_association_role_without_a_membership(): void
    {
        $member = $this->createMember('John', 'Doe', type: MemberType::Honorary);

        $this->assertTrue($member->holdsAssociationRole(hasActiveMembership: false, permanentTypes: [MemberType::Honorary]));
    }

    public function test_a_type_left_out_of_the_permanent_types_needs_a_membership(): void
    {
        $member = $this->createMember('John', 'Doe', type: MemberType::Honorary);

        $this->assertFalse($member->holdsAssociationRole(hasActiveMembership: false, permanentTypes: []));
        $this->assertTrue($member->holdsAssociationRole(hasActiveMembership: true, permanentTypes: []));
    }

    #[DataProvider('nonActiveStatuses')]
    public function test_a_member_whose_record_is_not_active_never_holds_the_association_role(MemberStatus $status): void
    {
        $regular = $this->createMember('John', 'Doe', $status);
        $honorary = $this->createMember('Jane', 'Smith', $status, MemberType::Honorary);

        $this->assertFalse($regular->holdsAssociationRole(hasActiveMembership: true, permanentTypes: [MemberType::Honorary]));
        $this->assertFalse($honorary->holdsAssociationRole(hasActiveMembership: true, permanentTypes: [MemberType::Honorary]));
    }

    /**
     * @return array<string, array{MemberStatus}>
     */
    public static function nonActiveStatuses(): array
    {
        return [
            'inactive' => [MemberStatus::Inactive],
            'suspended' => [MemberStatus::Suspended],
            'expelled' => [MemberStatus::Expelled],
        ];
    }

    public function test_the_rule_can_be_applied_without_building_the_entity(): void
    {
        $this->assertTrue(Member::qualifiesForAssociationRole(MemberStatus::Active, MemberType::Founder, false, [MemberType::Founder]));
        $this->assertFalse(Member::qualifiesForAssociationRole(MemberStatus::Suspended, MemberType::Founder, true, [MemberType::Founder]));
    }

    private function createMember(
        string $firstName,
        string $lastName,
        MemberStatus $status = MemberStatus::Active,
        MemberType $type = MemberType::Regular,
    ): Member {
        return new Member(
            id: MemberId::generate(),
            memberNumber: MemberNumber::generate(2026, 1),
            firstName: $firstName,
            lastName: $lastName,
            memberType: $type,
            status: $status,
            joinedAt: new DateTimeImmutable,
        );
    }
}
