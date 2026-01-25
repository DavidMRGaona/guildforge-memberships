<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\FeeStructure;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\ValueObjects\FeeStructureId;
use Modules\Memberships\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class FeeStructureTest extends TestCase
{
    public function test_it_creates_fee_structure_with_required_data(): void
    {
        $id = FeeStructureId::generate();
        $memberType = MemberType::Regular;
        $periodType = MembershipPeriodType::CalendarYear;
        $amount = Money::fromAmount(50.00, 'EUR');
        $validFrom = new DateTimeImmutable('2026-01-01');

        $feeStructure = new FeeStructure(
            id: $id,
            memberType: $memberType,
            periodType: $periodType,
            amount: $amount,
            validFrom: $validFrom,
        );

        $this->assertInstanceOf(FeeStructure::class, $feeStructure);
        $this->assertTrue($id->equals($feeStructure->id));
        $this->assertEquals($memberType, $feeStructure->memberType);
        $this->assertEquals($periodType, $feeStructure->periodType);
        $this->assertTrue($amount->equals($feeStructure->amount));
        $this->assertNull($feeStructure->prorationRules);
        $this->assertEquals($validFrom, $feeStructure->validFrom);
        $this->assertNull($feeStructure->validUntil);
        $this->assertNull($feeStructure->description);
        $this->assertFalse($feeStructure->isDefault);
        $this->assertNull($feeStructure->createdAt);
    }

    public function test_it_creates_fee_structure_with_all_data(): void
    {
        $id = FeeStructureId::generate();
        $memberType = MemberType::Student;
        $periodType = MembershipPeriodType::AcademicYear;
        $amount = Money::fromAmount(30.00, 'EUR');
        $prorationRules = ['monthly' => 2.50, 'minimum' => 10.00];
        $validFrom = new DateTimeImmutable('2026-09-01');
        $validUntil = new DateTimeImmutable('2027-08-31');
        $createdAt = new DateTimeImmutable('2026-08-01');

        $feeStructure = new FeeStructure(
            id: $id,
            memberType: $memberType,
            periodType: $periodType,
            amount: $amount,
            prorationRules: $prorationRules,
            validFrom: $validFrom,
            validUntil: $validUntil,
            description: 'Tarifa estudiantes curso 2026-2027',
            isDefault: true,
            createdAt: $createdAt,
        );

        $this->assertEquals($prorationRules, $feeStructure->prorationRules);
        $this->assertEquals($validUntil, $feeStructure->validUntil);
        $this->assertEquals('Tarifa estudiantes curso 2026-2027', $feeStructure->description);
        $this->assertTrue($feeStructure->isDefault);
        $this->assertEquals($createdAt, $feeStructure->createdAt);
    }

    public function test_it_can_update_amount(): void
    {
        $feeStructure = $this->createFeeStructure();
        $newAmount = Money::fromAmount(60.00, 'EUR');

        $feeStructure->updateAmount($newAmount);

        $this->assertTrue($newAmount->equals($feeStructure->amount));
    }

    public function test_it_can_set_valid_until(): void
    {
        $feeStructure = $this->createFeeStructure();
        $validUntil = new DateTimeImmutable('2026-12-31');

        $feeStructure->setValidUntil($validUntil);

        $this->assertEquals($validUntil, $feeStructure->validUntil);
    }

    public function test_it_checks_if_is_currently_valid(): void
    {
        $now = new DateTimeImmutable('2026-06-15');

        $validFeeStructure = new FeeStructure(
            id: FeeStructureId::generate(),
            memberType: MemberType::Regular,
            periodType: MembershipPeriodType::CalendarYear,
            amount: Money::fromAmount(50.00, 'EUR'),
            validFrom: new DateTimeImmutable('2026-01-01'),
            validUntil: new DateTimeImmutable('2026-12-31'),
        );

        $expiredFeeStructure = new FeeStructure(
            id: FeeStructureId::generate(),
            memberType: MemberType::Regular,
            periodType: MembershipPeriodType::CalendarYear,
            amount: Money::fromAmount(45.00, 'EUR'),
            validFrom: new DateTimeImmutable('2025-01-01'),
            validUntil: new DateTimeImmutable('2025-12-31'),
        );

        $futureFeesStructure = new FeeStructure(
            id: FeeStructureId::generate(),
            memberType: MemberType::Regular,
            periodType: MembershipPeriodType::CalendarYear,
            amount: Money::fromAmount(55.00, 'EUR'),
            validFrom: new DateTimeImmutable('2027-01-01'),
        );

        $this->assertTrue($validFeeStructure->isCurrentlyValid($now));
        $this->assertFalse($expiredFeeStructure->isCurrentlyValid($now));
        $this->assertFalse($futureFeesStructure->isCurrentlyValid($now));
    }

    public function test_it_can_set_as_default(): void
    {
        $feeStructure = $this->createFeeStructure();

        $feeStructure->setAsDefault();

        $this->assertTrue($feeStructure->isDefault);
    }

    public function test_it_can_unset_as_default(): void
    {
        $feeStructure = new FeeStructure(
            id: FeeStructureId::generate(),
            memberType: MemberType::Regular,
            periodType: MembershipPeriodType::CalendarYear,
            amount: Money::fromAmount(50.00, 'EUR'),
            validFrom: new DateTimeImmutable('2026-01-01'),
            isDefault: true,
        );

        $feeStructure->unsetAsDefault();

        $this->assertFalse($feeStructure->isDefault);
    }

    private function createFeeStructure(): FeeStructure
    {
        return new FeeStructure(
            id: FeeStructureId::generate(),
            memberType: MemberType::Regular,
            periodType: MembershipPeriodType::CalendarYear,
            amount: Money::fromAmount(50.00, 'EUR'),
            validFrom: new DateTimeImmutable('2026-01-01'),
        );
    }
}
