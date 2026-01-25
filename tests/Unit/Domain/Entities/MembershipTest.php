<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Membership;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Exceptions\InvalidMembershipTransitionException;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use PHPUnit\Framework\TestCase;

final class MembershipTest extends TestCase
{
    public function test_it_creates_membership_with_required_data(): void
    {
        $id = MembershipId::generate();
        $memberId = MemberId::generate();
        $periodType = MembershipPeriodType::CalendarYear;
        $startDate = new DateTimeImmutable('2026-01-01');
        $endDate = new DateTimeImmutable('2026-12-31');
        $status = MembershipStatus::Pending;

        $membership = new Membership(
            id: $id,
            memberId: $memberId,
            periodType: $periodType,
            startDate: $startDate,
            endDate: $endDate,
            status: $status,
        );

        $this->assertInstanceOf(Membership::class, $membership);
        $this->assertTrue($id->equals($membership->id));
        $this->assertTrue($memberId->equals($membership->memberId));
        $this->assertEquals($periodType, $membership->periodType);
        $this->assertEquals($startDate, $membership->startDate);
        $this->assertEquals($endDate, $membership->endDate);
        $this->assertEquals($status, $membership->status);
        $this->assertNull($membership->activatedAt);
        $this->assertNull($membership->cancelledAt);
        $this->assertNull($membership->notes);
        $this->assertNull($membership->createdAt);
    }

    public function test_it_creates_membership_with_all_data(): void
    {
        $id = MembershipId::generate();
        $memberId = MemberId::generate();
        $periodType = MembershipPeriodType::AcademicYear;
        $startDate = new DateTimeImmutable('2026-09-01');
        $endDate = new DateTimeImmutable('2027-06-30');
        $status = MembershipStatus::Active;
        $activatedAt = new DateTimeImmutable('2026-09-01 10:00:00');
        $createdAt = new DateTimeImmutable('2026-08-15 12:00:00');

        $membership = new Membership(
            id: $id,
            memberId: $memberId,
            periodType: $periodType,
            startDate: $startDate,
            endDate: $endDate,
            status: $status,
            activatedAt: $activatedAt,
            cancelledAt: null,
            notes: 'Academic year membership',
            createdAt: $createdAt,
        );

        $this->assertEquals($activatedAt, $membership->activatedAt);
        $this->assertEquals('Academic year membership', $membership->notes);
        $this->assertEquals($createdAt, $membership->createdAt);
    }

    public function test_it_can_activate(): void
    {
        $membership = $this->createMembership(MembershipStatus::Pending);

        $membership->activate();

        $this->assertEquals(MembershipStatus::Active, $membership->status);
        $this->assertInstanceOf(DateTimeImmutable::class, $membership->activatedAt);
    }

    public function test_it_cannot_activate_if_already_active(): void
    {
        $membership = $this->createMembership(MembershipStatus::Active);

        $this->expectException(InvalidMembershipTransitionException::class);

        $membership->activate();
    }

    public function test_it_can_expire(): void
    {
        $membership = $this->createMembership(MembershipStatus::Active);

        $membership->expire();

        $this->assertEquals(MembershipStatus::Expired, $membership->status);
    }

    public function test_it_can_cancel(): void
    {
        $membership = $this->createMembership(MembershipStatus::Active);

        $membership->cancel();

        $this->assertEquals(MembershipStatus::Cancelled, $membership->status);
        $this->assertInstanceOf(DateTimeImmutable::class, $membership->cancelledAt);
    }

    public function test_it_checks_if_is_active(): void
    {
        $activeMembership = $this->createMembership(MembershipStatus::Active);
        $pendingMembership = $this->createMembership(MembershipStatus::Pending);

        $this->assertTrue($activeMembership->isActive());
        $this->assertFalse($pendingMembership->isActive());
    }

    public function test_it_checks_if_is_within_period(): void
    {
        $now = new DateTimeImmutable('2026-06-15');
        $startDate = new DateTimeImmutable('2026-01-01');
        $endDate = new DateTimeImmutable('2026-12-31');

        $membership = new Membership(
            id: MembershipId::generate(),
            memberId: MemberId::generate(),
            periodType: MembershipPeriodType::CalendarYear,
            startDate: $startDate,
            endDate: $endDate,
            status: MembershipStatus::Active,
        );

        $this->assertTrue($membership->isWithinPeriod($now));
        $this->assertFalse($membership->isWithinPeriod(new DateTimeImmutable('2025-12-31')));
        $this->assertFalse($membership->isWithinPeriod(new DateTimeImmutable('2027-01-01')));
    }

    public function test_it_checks_if_is_expired(): void
    {
        $now = new DateTimeImmutable('2027-01-15');
        $startDate = new DateTimeImmutable('2026-01-01');
        $endDate = new DateTimeImmutable('2026-12-31');

        $expiredMembership = new Membership(
            id: MembershipId::generate(),
            memberId: MemberId::generate(),
            periodType: MembershipPeriodType::CalendarYear,
            startDate: $startDate,
            endDate: $endDate,
            status: MembershipStatus::Active,
        );

        $activeMembership = new Membership(
            id: MembershipId::generate(),
            memberId: MemberId::generate(),
            periodType: MembershipPeriodType::CalendarYear,
            startDate: new DateTimeImmutable('2026-01-01'),
            endDate: new DateTimeImmutable('2027-12-31'),
            status: MembershipStatus::Active,
        );

        $this->assertTrue($expiredMembership->isExpired($now));
        $this->assertFalse($activeMembership->isExpired($now));
    }

    private function createMembership(MembershipStatus $status): Membership
    {
        return new Membership(
            id: MembershipId::generate(),
            memberId: MemberId::generate(),
            periodType: MembershipPeriodType::CalendarYear,
            startDate: new DateTimeImmutable('2026-01-01'),
            endDate: new DateTimeImmutable('2026-12-31'),
            status: $status,
        );
    }
}
