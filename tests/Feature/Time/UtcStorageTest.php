<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Time;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;
use Modules\Memberships\Application\DTOs\FeeStructureDTO;
use Modules\Memberships\Application\DTOs\MemberDTO;
use Modules\Memberships\Application\DTOs\MembershipDTO;
use Modules\Memberships\Application\DTOs\MembershipFeeDTO;
use Modules\Memberships\Application\Services\MemberNumberGeneratorInterface;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Domain\Entities\FeeStructure;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Entities\Membership;
use Modules\Memberships\Domain\Entities\MembershipFee;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Repositories\FeeStructureRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\FeeStructureId;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;
use Modules\Memberships\Domain\ValueObjects\MembershipFeeId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Domain\ValueObjects\Money;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\ListMembers;
use Modules\Memberships\Filament\Widgets\ExpiringMembershipsWidget;
use Modules\Memberships\Filament\Widgets\OverdueFeesWidget;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
use Modules\Memberships\Notifications\MembershipExpiredNotification;
use Modules\Memberships\Notifications\MembershipExpiringNotification;
use Modules\Memberships\Notifications\PaymentOverdueNotification;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * With UTC storage the instants in the database are UTC, but the dates of the memberships are
 * calendar days of the site and whoever reads them sees the Madrid day.
 */
final class UtcStorageTest extends ModuleTestCase
{
    use InteractsWithModulePanel;
    use UsesUtcStorage;

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    /** 12:00 in Madrid (CEST). */
    private const string INSTANT = '2026-07-01T10:00:00Z';

    private const string MADRID_ISO = '2026-07-01T12:00:00+02:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        app()->setLocale('es');
    }

    public function test_every_model_stores_instants_in_the_storage_timezone(): void
    {
        $files = glob(dirname(__DIR__, 3).'/src/Infrastructure/Persistence/Eloquent/Models/*.php') ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $class = 'Modules\\Memberships\\Infrastructure\\Persistence\\Eloquent\\Models\\'.basename($file, '.php');

            $this->assertContains(StoresInstantsInStorageTimezone::class, class_uses_recursive($class), $class);
        }
    }

    public function test_a_membership_ending_today_is_active_until_midnight_in_madrid(): void
    {
        $this->membershipRow(end: '2026-10-25');
        $repository = $this->app->make(MembershipRepositoryInterface::class);
        $membership = $repository->findByMemberId(MemberId::fromString($this->memberRow()->id))[0];

        // 23:30 in Madrid on the last day (the clocks went back that night)
        $this->assertFalse($membership->isExpired(new DateTimeImmutable('2026-10-25T22:30:00Z')));
        $this->assertTrue($membership->isWithinPeriod(new DateTimeImmutable('2026-10-25T22:30:00Z')));
        // 00:30 in Madrid the day after
        $this->assertTrue($membership->isExpired(new DateTimeImmutable('2026-10-25T23:30:00Z')));
        $this->assertFalse($membership->isWithinPeriod(new DateTimeImmutable('2026-10-25T23:30:00Z')));
    }

    public function test_the_expiry_command_leaves_a_membership_ending_today_alone_until_madrid_midnight(): void
    {
        $membership = $this->membershipRow(end: '2026-10-25');

        $this->travelTo(CarbonImmutable::parse('2026-10-25T22:30:00Z'));
        $this->processExpired();
        $this->assertSame('active', $membership->refresh()->status->value);

        $this->travelTo(CarbonImmutable::parse('2026-10-25T23:30:00Z'));
        $this->processExpired();
        $this->assertSame('expired', $membership->refresh()->status->value);
    }

    public function test_a_fee_structure_is_valid_through_its_last_day_in_madrid(): void
    {
        $repository = $this->app->make(FeeStructureRepositoryInterface::class);
        $structure = new FeeStructure(
            id: FeeStructureId::generate(),
            memberType: MemberType::Regular,
            periodType: MembershipPeriodType::CalendarYear,
            amount: Money::fromCents(3000, 'EUR'),
            validFrom: CalendarDate::parse('2026-01-01'),
            validUntil: CalendarDate::parse('2026-10-25'),
        );
        $repository->save($structure);
        $stored = $repository->findOrFail($structure->id);

        $this->assertTrue($stored->isCurrentlyValid(new DateTimeImmutable('2026-10-25T22:30:00Z')));
        $this->assertFalse($stored->isCurrentlyValid(new DateTimeImmutable('2026-10-25T23:30:00Z')));
        $this->assertFalse($stored->isCurrentlyValid(new DateTimeImmutable('2025-12-31T22:30:00Z')));
        $this->assertTrue($stored->isCurrentlyValid(new DateTimeImmutable('2025-12-31T23:30:00Z')));
    }

    public function test_a_fee_is_overdue_from_the_day_after_its_due_date_in_madrid(): void
    {
        $fee = new MembershipFee(
            id: MembershipFeeId::generate(),
            membershipId: MembershipId::generate(),
            amount: Money::fromCents(3000, 'EUR'),
            dueDate: CalendarDate::parse('2026-10-25'),
        );

        $this->assertFalse($fee->isOverdue(new DateTimeImmutable('2026-10-25T22:30:00Z')));
        $this->assertTrue($fee->isOverdue(new DateTimeImmutable('2026-10-25T23:30:00Z')));
    }

    public function test_a_member_number_generated_after_new_year_in_madrid_uses_the_new_year(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-31T23:30:00Z'));

        $number = $this->app->make(MemberNumberGeneratorInterface::class)->generate();

        $this->assertSame(2027, $number->year());
    }

    public function test_the_calendar_dates_are_stored_as_the_day_and_read_back_as_the_same_day(): void
    {
        $repository = $this->app->make(MembershipRepositoryInterface::class);
        $member = $this->memberRow();
        $membership = new Membership(
            id: MembershipId::generate(),
            memberId: MemberId::fromString($member->id),
            periodType: MembershipPeriodType::CalendarYear,
            startDate: CalendarDate::parse('2026-01-01'),
            endDate: CalendarDate::parse('2026-12-31'),
            status: MembershipStatus::Pending,
        );
        $repository->save($membership);

        $this->assertStringStartsWith('2026-01-01', DB::table('memberships_memberships')->value('start_date'));
        $this->assertStringStartsWith('2026-12-31', DB::table('memberships_memberships')->value('end_date'));

        $stored = $repository->findOrFail($membership->id);
        $this->assertSame('2026-01-01', $stored->startDate->format('Y-m-d'));
        $this->assertSame('Europe/Madrid', $stored->endDate->getTimezone()->getName());
    }

    public function test_the_instants_are_stored_in_utc_and_serialized_with_the_madrid_offset(): void
    {
        $instant = new DateTimeImmutable(self::INSTANT);
        $memberId = MemberId::generate();
        $this->app->make(MemberRepositoryInterface::class)->save(new Member(
            id: $memberId,
            memberNumber: new MemberNumber('2026-0099'),
            firstName: 'John',
            lastName: 'Doe',
            joinedAt: $instant,
        ));
        $stored = $this->app->make(MemberRepositoryInterface::class)->findOrFail($memberId);

        $this->assertSame('2026-07-01 10:00:00', DB::table('memberships_members')->value('joined_at'));
        $this->assertSame(self::MADRID_ISO, MemberDTO::fromEntity($stored)->joinedAt);

        $membership = new Membership(
            id: MembershipId::generate(),
            memberId: $memberId,
            periodType: MembershipPeriodType::CalendarYear,
            startDate: CalendarDate::parse('2026-01-01'),
            endDate: CalendarDate::parse('2026-12-31'),
            status: MembershipStatus::Active,
            activatedAt: $instant,
            cancelledAt: $instant,
        );
        $membershipDto = MembershipDTO::fromEntity($membership);
        $this->assertSame(self::MADRID_ISO, $membershipDto->activatedAt);
        $this->assertSame(self::MADRID_ISO, $membershipDto->cancelledAt);
        $this->assertSame('2026-12-31', $membershipDto->endDate);

        $fee = new MembershipFee(
            id: MembershipFeeId::generate(),
            membershipId: $membership->id,
            amount: Money::fromCents(3000, 'EUR'),
            dueDate: CalendarDate::parse('2026-07-01'),
            paidAt: $instant,
            createdAt: $instant,
        );
        $feeDto = MembershipFeeDTO::fromEntity($fee);
        $this->assertSame(self::MADRID_ISO, $feeDto->paidAt);
        $this->assertSame(self::MADRID_ISO, $feeDto->createdAt);
        $this->assertSame('2026-07-01', $feeDto->dueDate);

        $structure = new FeeStructure(
            id: FeeStructureId::generate(),
            memberType: MemberType::Regular,
            periodType: MembershipPeriodType::CalendarYear,
            amount: Money::fromCents(3000, 'EUR'),
            validFrom: CalendarDate::parse('2026-01-01'),
            createdAt: $instant,
        );
        $this->assertSame(self::MADRID_ISO, FeeStructureDTO::fromEntity($structure)->createdAt);
    }

    public function test_the_expiring_notification_shows_the_calendar_day(): void
    {
        $mail = (new MembershipExpiringNotification('John Doe', CalendarDate::parse('2026-07-01'), 5))->toMail(new \stdClass);

        $this->assertStringContainsString('01/07/2026', implode(' ', $mail->introLines));
    }

    public function test_the_expired_notification_shows_the_calendar_day(): void
    {
        $mail = (new MembershipExpiredNotification('John Doe', CalendarDate::parse('2026-07-01')))->toMail(new \stdClass);

        $this->assertStringContainsString('01/07/2026', implode(' ', $mail->introLines));
    }

    public function test_the_overdue_notification_shows_the_calendar_day(): void
    {
        $mail = (new PaymentOverdueNotification('John Doe', '30,00 €', CalendarDate::parse('2026-07-01'), 3))->toMail(new \stdClass);

        $this->assertStringContainsString('01/07/2026', implode(' ', $mail->introLines));
    }

    public function test_the_member_list_shows_the_join_day_in_madrid(): void
    {
        $this->registerOnAdminPanel([MemberResource::class]);
        $this->actingAsPanelAdmin();
        // 00:30 on 1 July in Madrid
        $member = $this->memberRow(joinedAt: '2026-06-30T22:30:00Z');

        Livewire::test(ListMembers::class)->assertTableColumnFormattedStateSet('joined_at', '01/07/2026', $member);
    }

    public function test_the_expiring_widget_shows_the_calendar_day_and_the_days_left(): void
    {
        $this->registerOnAdminPanel([MemberResource::class]);
        $this->actingAsPanelAdmin();
        // 00:30 on 25 October in Madrid, five days before the end
        $this->travelTo(CarbonImmutable::parse('2026-10-24T22:30:00Z'));
        $membership = $this->membershipRow(end: '2026-10-30');

        Livewire::test(ExpiringMembershipsWidget::class)
            ->assertCanSeeTableRecords([$membership])
            ->assertTableColumnFormattedStateSet('end_date', '30/10/2026', $membership)
            ->assertTableColumnStateSet('days_remaining', 5, $membership);
    }

    public function test_the_expiring_widget_keeps_a_membership_ending_today_until_madrid_midnight(): void
    {
        $this->registerOnAdminPanel([MemberResource::class]);
        $this->actingAsPanelAdmin();
        $membership = $this->membershipRow(end: '2026-10-25');

        $this->travelTo(CarbonImmutable::parse('2026-10-25T22:30:00Z'));
        Livewire::test(ExpiringMembershipsWidget::class)->assertCanSeeTableRecords([$membership]);

        $this->travelTo(CarbonImmutable::parse('2026-10-25T23:30:00Z'));
        Livewire::test(ExpiringMembershipsWidget::class)->assertCanNotSeeTableRecords([$membership]);
    }

    public function test_the_overdue_widget_shows_the_due_day_and_the_days_overdue(): void
    {
        $this->registerOnAdminPanel([MemberResource::class]);
        $this->actingAsPanelAdmin();
        // 00:30 on 25 October in Madrid
        $this->travelTo(CarbonImmutable::parse('2026-10-24T22:30:00Z'));
        $fee = $this->feeRow('2026-10-20');

        Livewire::test(OverdueFeesWidget::class)
            ->assertCanSeeTableRecords([$fee])
            ->assertTableColumnFormattedStateSet('due_date', '20/10/2026', $fee)
            ->assertTableColumnStateSet('days_overdue', 5, $fee);
    }

    public function test_the_overdue_widget_waits_for_the_day_after_the_due_date_in_madrid(): void
    {
        $this->registerOnAdminPanel([MemberResource::class]);
        $this->actingAsPanelAdmin();
        $fee = $this->feeRow('2026-10-25');

        $this->travelTo(CarbonImmutable::parse('2026-10-25T22:30:00Z'));
        Livewire::test(OverdueFeesWidget::class)->assertCanNotSeeTableRecords([$fee]);

        $this->travelTo(CarbonImmutable::parse('2026-10-25T23:30:00Z'));
        Livewire::test(OverdueFeesWidget::class)->assertCanSeeTableRecords([$fee]);
    }

    private function memberRow(string $joinedAt = self::INSTANT): MemberModel
    {
        static $number = 1;

        return MemberModel::query()->first() ?? MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2026-%04d', $number++),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.test',
            'member_type' => 'regular',
            'status' => 'active',
            'joined_at' => new DateTimeImmutable($joinedAt),
        ]);
    }

    private function membershipRow(string $end): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $this->memberRow()->id,
            'period_type' => 'calendar_year',
            'start_date' => '2026-01-01',
            'end_date' => $end,
            'status' => 'active',
        ]);
    }

    private function feeRow(string $due): MembershipFeeModel
    {
        return MembershipFeeModel::create([
            'id' => (string) Str::uuid(),
            'membership_id' => $this->membershipRow('2026-12-31')->id,
            'amount' => 30,
            'currency' => 'EUR',
            'due_date' => $due,
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
