<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Services;

use Illuminate\Support\Str;
use Modules\Memberships\Application\DTOs\RecordPaymentDTO;
use Modules\Memberships\Application\Services\MembershipFeeServiceInterface;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Modules\ModuleTestCase;

final class MembershipFeeServiceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    public function test_recording_a_payment_stores_the_method_reference_and_date_on_that_fee(): void
    {
        $service = $this->app->make(MembershipFeeServiceInterface::class);
        $membershipId = $this->membership();
        $fee = $service->createFee($membershipId, 3000, 'EUR', '2026-01-31');
        $other = $service->createFee($membershipId, 1500, 'EUR', '2026-06-30');

        $result = $service->recordPayment(new RecordPaymentDTO(
            feeId: $fee->id,
            paymentMethod: PaymentMethod::BankTransfer->value,
            transactionReference: 'TRF-2026-001',
            paidAt: '2026-01-15 10:30:00',
        ));

        $this->assertTrue($result->isPaid);
        $this->assertSame('bank_transfer', $result->paymentMethod);
        $this->assertSame('TRF-2026-001', $result->transactionReference);
        $this->assertSame(3000, $result->amountCents);

        $stored = MembershipFeeModel::query()->findOrFail($fee->id);
        $this->assertSame(PaymentMethod::BankTransfer, $stored->payment_method);
        $this->assertSame('TRF-2026-001', $stored->transaction_reference);
        $this->assertSame('2026-01-15 10:30:00', $stored->paid_at?->format('Y-m-d H:i:s'));
        $this->assertSame('30.00', (string) $stored->amount);
        $this->assertSame('2026-01-31', $stored->due_date->format('Y-m-d'));

        $untouched = MembershipFeeModel::query()->findOrFail($other->id);
        $this->assertNull($untouched->paid_at);
        $this->assertNull($untouched->payment_method);
    }

    public function test_a_payment_without_date_is_dated_now(): void
    {
        $service = $this->app->make(MembershipFeeServiceInterface::class);
        $fee = $service->createFee($this->membership(), 3000, 'EUR', '2026-01-31');

        $this->freezeSecond();
        $service->recordPayment(new RecordPaymentDTO(feeId: $fee->id, paymentMethod: PaymentMethod::Cash->value));

        $stored = MembershipFeeModel::query()->findOrFail($fee->id);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $stored->paid_at?->format('Y-m-d H:i:s'));
        $this->assertSame(PaymentMethod::Cash, $stored->payment_method);
        $this->assertNull($stored->transaction_reference);
    }

    private function membership(): string
    {
        $member = MemberModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'member_type' => 'regular',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'pending',
        ])->id;
    }
}
