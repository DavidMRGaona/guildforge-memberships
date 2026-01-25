<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\MembershipFee;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Domain\ValueObjects\MembershipFeeId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Domain\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class MembershipFeeTest extends TestCase
{
    public function test_it_creates_fee_with_required_data(): void
    {
        $id = MembershipFeeId::generate();
        $membershipId = MembershipId::generate();
        $amount = Money::fromAmount(50.00, 'EUR');
        $dueDate = new DateTimeImmutable('2026-01-31');

        $fee = new MembershipFee(
            id: $id,
            membershipId: $membershipId,
            amount: $amount,
            dueDate: $dueDate,
        );

        $this->assertInstanceOf(MembershipFee::class, $fee);
        $this->assertTrue($id->equals($fee->id));
        $this->assertTrue($membershipId->equals($fee->membershipId));
        $this->assertTrue($amount->equals($fee->amount));
        $this->assertEquals($dueDate, $fee->dueDate);
        $this->assertNull($fee->paidAt);
        $this->assertNull($fee->paymentMethod);
        $this->assertNull($fee->transactionReference);
        $this->assertNull($fee->notes);
        $this->assertNull($fee->createdAt);
    }

    public function test_it_creates_fee_with_all_data(): void
    {
        $id = MembershipFeeId::generate();
        $membershipId = MembershipId::generate();
        $amount = Money::fromAmount(30.00, 'EUR');
        $dueDate = new DateTimeImmutable('2026-01-31');
        $paidAt = new DateTimeImmutable('2026-01-15');
        $paymentMethod = PaymentMethod::BankTransfer;
        $createdAt = new DateTimeImmutable('2026-01-01');

        $fee = new MembershipFee(
            id: $id,
            membershipId: $membershipId,
            amount: $amount,
            dueDate: $dueDate,
            paidAt: $paidAt,
            paymentMethod: $paymentMethod,
            transactionReference: 'TXN-2026-001',
            notes: 'Student discount applied',
            createdAt: $createdAt,
        );

        $this->assertEquals($paidAt, $fee->paidAt);
        $this->assertEquals($paymentMethod, $fee->paymentMethod);
        $this->assertEquals('TXN-2026-001', $fee->transactionReference);
        $this->assertEquals('Student discount applied', $fee->notes);
        $this->assertEquals($createdAt, $fee->createdAt);
    }

    public function test_it_can_record_payment(): void
    {
        $fee = $this->createFee();

        $fee->recordPayment(
            paymentMethod: PaymentMethod::Cash,
            transactionReference: 'CASH-2026-001',
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $fee->paidAt);
        $this->assertEquals(PaymentMethod::Cash, $fee->paymentMethod);
        $this->assertEquals('CASH-2026-001', $fee->transactionReference);
    }

    public function test_it_checks_if_is_paid(): void
    {
        $paidFee = $this->createFee();
        $paidFee->recordPayment(PaymentMethod::Card, 'CARD-2026-001');

        $unpaidFee = $this->createFee();

        $this->assertTrue($paidFee->isPaid());
        $this->assertFalse($unpaidFee->isPaid());
    }

    public function test_it_checks_if_is_overdue(): void
    {
        $now = new DateTimeImmutable('2026-02-15');

        $overdueFee = new MembershipFee(
            id: MembershipFeeId::generate(),
            membershipId: MembershipId::generate(),
            amount: Money::fromAmount(50.00, 'EUR'),
            dueDate: new DateTimeImmutable('2026-01-31'),
        );

        $upToDateFee = new MembershipFee(
            id: MembershipFeeId::generate(),
            membershipId: MembershipId::generate(),
            amount: Money::fromAmount(50.00, 'EUR'),
            dueDate: new DateTimeImmutable('2026-03-31'),
        );

        $paidButOverdueFee = new MembershipFee(
            id: MembershipFeeId::generate(),
            membershipId: MembershipId::generate(),
            amount: Money::fromAmount(50.00, 'EUR'),
            dueDate: new DateTimeImmutable('2026-01-31'),
            paidAt: new DateTimeImmutable('2026-02-01'),
            paymentMethod: PaymentMethod::Cash,
        );

        $this->assertTrue($overdueFee->isOverdue($now));
        $this->assertFalse($upToDateFee->isOverdue($now));
        $this->assertFalse($paidButOverdueFee->isOverdue($now));
    }

    private function createFee(): MembershipFee
    {
        return new MembershipFee(
            id: MembershipFeeId::generate(),
            membershipId: MembershipId::generate(),
            amount: Money::fromAmount(50.00, 'EUR'),
            dueDate: new DateTimeImmutable('2026-01-31'),
        );
    }
}
