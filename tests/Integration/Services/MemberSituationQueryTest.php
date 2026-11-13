<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Memberships\Application\DTOs\MemberSituationDTO;
use Modules\Memberships\Application\Services\MemberSituationQueryInterface;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * What the profile's member tab shows: the record linked to the account, its current
 * membership, the fees still to pay and the last one paid (memberships#79).
 */
final class MemberSituationQueryTest extends ModuleTestCase
{
    use UsesUtcStorage;

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->user = UserModel::factory()->create();
    }

    public function test_an_account_without_member_record_has_no_situation(): void
    {
        $this->member(userId: null);

        $this->assertNull($this->situationQuery()->forUser((string) $this->user->id));
    }

    public function test_a_deleted_member_record_gives_no_situation(): void
    {
        $this->member()->delete();

        $this->assertNull($this->situationQuery()->forUser((string) $this->user->id));
    }

    public function test_the_record_gives_number_type_status_and_join_day(): void
    {
        $this->member(type: 'student', status: 'suspended', joinedAt: '2024-03-05 10:00:00');

        $situation = $this->situation();

        $this->assertSame('2026-0042', $situation->memberNumber);
        $this->assertSame('student', $situation->memberType);
        $this->assertSame('suspended', $situation->status);
        $this->assertSame('2024-03-05', $situation->joinedOn);
        $this->assertFalse($situation->permanent);
        $this->assertNull($situation->membership);
        $this->assertSame([], $situation->pendingFees);
        $this->assertNull($situation->lastPayment);
    }

    public function test_a_permanent_type_is_marked(): void
    {
        $this->member(type: 'honorary');

        $this->assertTrue($this->situation()->permanent);
    }

    public function test_the_active_membership_wins_over_a_newer_pending_one(): void
    {
        $member = $this->member();
        $this->membership($member, 'active', '2026-01-01', '2026-12-31');
        $this->membership($member, 'pending', '2027-01-01', '2027-12-31');

        $membership = $this->situation()->membership;

        $this->assertNotNull($membership);
        $this->assertSame('active', $membership->status);
        $this->assertSame('calendar_year', $membership->periodType);
        $this->assertSame('2026-01-01', $membership->startsOn);
        $this->assertSame('2026-12-31', $membership->endsOn);
    }

    public function test_without_active_membership_the_latest_one_is_shown(): void
    {
        $member = $this->member();
        $this->membership($member, 'expired', '2024-01-01', '2024-12-31');
        $this->membership($member, 'expired', '2025-01-01', '2025-12-31');

        $membership = $this->situation()->membership;

        $this->assertNotNull($membership);
        $this->assertSame('expired', $membership->status);
        $this->assertSame('2025-12-31', $membership->endsOn);
    }

    public function test_pending_fees_are_the_unpaid_ones_by_due_day_with_overdue_marked(): void
    {
        $member = $this->member();
        $current = $this->membership($member, 'active', '2026-01-01', '2026-12-31');
        $today = CalendarDate::today();

        $this->fee($current, $today->modify('+20 days')->format('Y-m-d'), '15.00');
        $this->fee($current, $today->modify('-3 days')->format('Y-m-d'), '30.50');
        $this->fee($current, $today->format('Y-m-d'), '10.00');
        $this->fee($current, $today->modify('-40 days')->format('Y-m-d'), '99.00', paidAt: '2026-01-15 12:00:00');

        $fees = $this->situation()->pendingFees;

        $this->assertCount(3, $fees);
        $this->assertSame([3050, 1000, 1500], array_map(static fn ($fee): int => $fee->amountCents, $fees));
        $this->assertSame(['EUR', 'EUR', 'EUR'], array_map(static fn ($fee): string => $fee->currency, $fees));
        // Due today is not overdue yet: the fee is overdue from the day after
        $this->assertSame([true, false, false], array_map(static fn ($fee): bool => $fee->overdue, $fees));
        $this->assertSame($today->modify('-3 days')->format('Y-m-d'), $fees[0]->dueOn);
        $this->assertSame(1, $this->situation()->overdueCount());
    }

    public function test_fees_of_a_cancelled_membership_are_not_pending(): void
    {
        $member = $this->member();
        $cancelled = $this->membership($member, 'cancelled', '2025-01-01', '2025-12-31');
        $this->fee($cancelled, '2025-02-01', '30.00');

        $this->assertSame([], $this->situation()->pendingFees);
        $this->assertSame(0, $this->situation()->overdueCount());
    }

    public function test_pending_fees_of_an_earlier_membership_still_count(): void
    {
        $member = $this->member();
        $old = $this->membership($member, 'expired', '2025-01-01', '2025-12-31');
        $this->membership($member, 'active', '2026-01-01', '2026-12-31');
        $this->fee($old, '2025-02-01', '30.00');

        $fees = $this->situation()->pendingFees;

        $this->assertCount(1, $fees);
        $this->assertTrue($fees[0]->overdue);
    }

    public function test_the_last_payment_is_the_most_recently_paid_fee_on_its_display_day(): void
    {
        $member = $this->member();
        $old = $this->membership($member, 'expired', '2025-01-01', '2025-12-31');
        $current = $this->membership($member, 'active', '2026-01-01', '2026-12-31');
        $this->fee($old, '2025-02-01', '25.00', paidAt: '2025-02-03 10:00:00');
        // 23:30 UTC on 9 March is already 10 March in Madrid
        $this->fee($current, '2026-02-01', '30.00', paidAt: '2026-03-09 23:30:00');

        $payment = $this->situation()->lastPayment;

        $this->assertNotNull($payment);
        $this->assertSame(3000, $payment->amountCents);
        $this->assertSame('EUR', $payment->currency);
        $this->assertSame('2026-03-10', $payment->paidOn);
    }

    public function test_the_situation_is_an_array_for_the_page(): void
    {
        $member = $this->member();
        $current = $this->membership($member, 'active', '2026-01-01', '2026-12-31');
        $this->fee($current, '2026-02-01', '30.00', paidAt: '2026-02-02 10:00:00');

        $this->assertSame([
            'memberNumber' => '2026-0042',
            'memberType' => 'regular',
            'status' => 'active',
            'joinedOn' => '2024-03-05',
            'permanent' => false,
            'membership' => [
                'status' => 'active',
                'periodType' => 'calendar_year',
                'startsOn' => '2026-01-01',
                'endsOn' => '2026-12-31',
            ],
            'pendingFees' => [],
            'lastPayment' => [
                'amountCents' => 3000,
                'currency' => 'EUR',
                'paidOn' => '2026-02-02',
            ],
        ], $this->situation()->toArray());
    }

    private function situationQuery(): MemberSituationQueryInterface
    {
        return $this->app->make(MemberSituationQueryInterface::class);
    }

    private function situation(): MemberSituationDTO
    {
        $situation = $this->situationQuery()->forUser((string) $this->user->id);
        $this->assertInstanceOf(MemberSituationDTO::class, $situation);

        return $situation;
    }

    private function member(
        string $type = 'regular',
        string $status = 'active',
        string $joinedAt = '2024-03-05 10:00:00',
        string|false|null $userId = false,
    ): MemberModel {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => '2026-0042',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.test',
            'member_type' => $type,
            'status' => $status,
            'user_id' => $userId === false ? $this->user->id : $userId,
            'joined_at' => CarbonImmutable::parse($joinedAt, 'UTC'),
        ]);
    }

    private function membership(MemberModel $member, string $status, string $start, string $end): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
        ]);
    }

    private function fee(MembershipModel $membership, string $dueOn, string $amount, ?string $paidAt = null): MembershipFeeModel
    {
        return MembershipFeeModel::create([
            'id' => (string) Str::uuid(),
            'membership_id' => $membership->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'due_date' => $dueOn,
            'paid_at' => $paidAt === null ? null : CarbonImmutable::parse($paidAt, 'UTC'),
        ]);
    }
}
