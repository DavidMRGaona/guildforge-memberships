<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Time;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Domain\Entities\FeeStructure;
use Modules\Memberships\Domain\Entities\Membership;
use Modules\Memberships\Domain\Entities\MembershipFee;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\ValueObjects\FeeStructureId;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MembershipFeeId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Domain\ValueObjects\Money;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Widgets\ExpiringMembershipsWidget;
use Modules\Memberships\Filament\Widgets\OverdueFeesWidget;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Dates of memberships, fees and tariffs are calendar days of the site: the last (or due) day
 * still counts and the next day is the first one past it. Pinned with explicit Madrid offsets
 * (CET, +01:00, on 25 October 2026) so it holds whatever zone the database stores in.
 */
final class BoundaryDaysTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    /** 23:30 on 25 October in Madrid. */
    private const string LAST_DAY = '2026-10-25T23:30:00+01:00';

    /** 00:30 on 26 October in Madrid. */
    private const string NEXT_DAY = '2026-10-26T00:30:00+01:00';

    public function test_membership_is_active_through_its_last_day_and_expired_the_next(): void
    {
        $membership = new Membership(
            id: MembershipId::generate(),
            memberId: MemberId::generate(),
            periodType: MembershipPeriodType::CalendarYear,
            startDate: CalendarDate::parse('2026-01-01'),
            endDate: CalendarDate::parse('2026-10-25'),
            status: MembershipStatus::Active,
        );

        $this->assertFalse($membership->isExpired(new DateTimeImmutable(self::LAST_DAY)));
        $this->assertTrue($membership->isWithinPeriod(new DateTimeImmutable(self::LAST_DAY)));
        $this->assertTrue($membership->isExpired(new DateTimeImmutable(self::NEXT_DAY)));
        $this->assertFalse($membership->isWithinPeriod(new DateTimeImmutable(self::NEXT_DAY)));
    }

    public function test_fee_structure_is_valid_through_its_last_day_and_not_the_next(): void
    {
        $structure = new FeeStructure(
            id: FeeStructureId::generate(),
            memberType: MemberType::Regular,
            periodType: MembershipPeriodType::CalendarYear,
            amount: Money::fromCents(3000, 'EUR'),
            validFrom: CalendarDate::parse('2026-01-01'),
            validUntil: CalendarDate::parse('2026-10-25'),
        );

        $this->assertTrue($structure->isCurrentlyValid(new DateTimeImmutable(self::LAST_DAY)));
        $this->assertFalse($structure->isCurrentlyValid(new DateTimeImmutable(self::NEXT_DAY)));
        $this->assertFalse($structure->isCurrentlyValid(new DateTimeImmutable('2025-12-31T23:30:00+01:00')));
        $this->assertTrue($structure->isCurrentlyValid(new DateTimeImmutable('2026-01-01T00:30:00+01:00')));
    }

    public function test_fee_is_not_overdue_on_its_due_day_and_is_the_next(): void
    {
        $fee = new MembershipFee(
            id: MembershipFeeId::generate(),
            membershipId: MembershipId::generate(),
            amount: Money::fromCents(3000, 'EUR'),
            dueDate: CalendarDate::parse('2026-10-25'),
        );

        $this->assertFalse($fee->isOverdue(new DateTimeImmutable(self::LAST_DAY)));
        $this->assertTrue($fee->isOverdue(new DateTimeImmutable(self::NEXT_DAY)));
    }

    public function test_expiry_command_waits_for_the_day_after_the_end_date(): void
    {
        $membership = $this->membershipRow('2026-10-25');

        $this->travelTo(CarbonImmutable::parse(self::LAST_DAY));
        $this->processExpired();
        $this->assertSame('active', $membership->refresh()->status->value);

        $this->travelTo(CarbonImmutable::parse(self::NEXT_DAY));
        $this->processExpired();
        $this->assertSame('expired', $membership->refresh()->status->value);
    }

    public function test_expiring_widget_lists_a_membership_ending_today_and_drops_it_the_next_day(): void
    {
        $this->registerOnAdminPanel([MemberResource::class]);
        $this->actingAsPanelAdmin();
        $membership = $this->membershipRow('2026-10-25');

        $this->travelTo(CarbonImmutable::parse(self::LAST_DAY));
        Livewire::test(ExpiringMembershipsWidget::class)
            ->assertCanSeeTableRecords([$membership])
            ->assertTableColumnStateSet('days_remaining', 0, $membership);

        $this->travelTo(CarbonImmutable::parse(self::NEXT_DAY));
        Livewire::test(ExpiringMembershipsWidget::class)->assertCanNotSeeTableRecords([$membership]);
    }

    public function test_overdue_widget_lists_a_fee_the_day_after_its_due_date_not_on_it(): void
    {
        $this->registerOnAdminPanel([MemberResource::class]);
        $this->actingAsPanelAdmin();
        $fee = MembershipFeeModel::create([
            'id' => (string) Str::uuid(),
            'membership_id' => $this->membershipRow('2026-12-31')->id,
            'amount' => 30,
            'currency' => 'EUR',
            'due_date' => '2026-10-25',
        ]);

        $this->travelTo(CarbonImmutable::parse(self::LAST_DAY));
        Livewire::test(OverdueFeesWidget::class)->assertCanNotSeeTableRecords([$fee]);

        $this->travelTo(CarbonImmutable::parse(self::NEXT_DAY));
        Livewire::test(OverdueFeesWidget::class)
            ->assertCanSeeTableRecords([$fee])
            ->assertTableColumnStateSet('days_overdue', 1, $fee);
    }

    private function membershipRow(string $end): MembershipModel
    {
        $member = MemberModel::query()->first() ?? MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => '2026-0001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.test',
            'member_type' => 'regular',
            'status' => 'active',
            'joined_at' => new DateTimeImmutable('2026-01-01T10:00:00Z'),
        ]);

        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => '2026-01-01',
            'end_date' => $end,
            'status' => 'active',
        ]);
    }

    private function processExpired(): void
    {
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ProcessExpiredMemberships::class));
        $command = $this->artisan('memberships:process-expired');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful();
    }
}
