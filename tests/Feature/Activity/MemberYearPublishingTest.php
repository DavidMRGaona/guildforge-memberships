<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\EventFake;
use Modules\Memberships\Application\DTOs\RecordPaymentDTO;
use Modules\Memberships\Application\Services\MembershipFeeServiceInterface;
use Modules\Memberships\Console\Commands\AnnounceMemberYears;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Infrastructure\Activity\MemberYearActivitySource;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Whatever changes the years an account was a member is told to the core (core#774), whichever
 * way the change was made: ActivityRecorded carries the account and the year, never more.
 */
final class MemberYearPublishingTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private int $numbers = 0;

    private EventFake $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15T12:00:00+02:00'));
        $this->events = Event::fake([ActivityRecorded::class]);
        // The publisher keeps the dispatcher it was built with
        $this->app->forgetInstance(ActivityPublisherInterface::class);
    }

    public function test_an_active_period_announces_each_year_it_covers(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);

        $this->period($member, MembershipStatus::Active, '2024-09-01', '2025-06-30');

        $this->assertRecorded($user, [2024, 2025]);
    }

    public function test_a_period_that_does_not_count_announces_nothing(): void
    {
        $member = $this->memberOf(UserModel::factory()->create());

        $this->period($member, MembershipStatus::Pending, '2024-01-01', '2024-12-31');

        $this->assertNothingRecorded();
    }

    public function test_activating_a_pending_period_announces_its_years(): void
    {
        $user = UserModel::factory()->create();
        $period = $this->period($this->memberOf($user), MembershipStatus::Pending, '2024-01-01', '2024-12-31');
        $this->forgetRecorded();

        $period->update(['status' => MembershipStatus::Active->value]);

        $this->assertRecorded($user, [2024]);
    }

    public function test_cancelling_a_period_announces_its_years(): void
    {
        $user = UserModel::factory()->create();
        $period = $this->period($this->memberOf($user), MembershipStatus::Active, '2024-01-01', '2024-12-31');
        $this->forgetRecorded();

        $period->update(['status' => MembershipStatus::Cancelled->value]);

        $this->assertRecorded($user, [2024]);
    }

    public function test_expiring_a_period_changes_nothing_and_announces_nothing(): void
    {
        $user = UserModel::factory()->create();
        $period = $this->period($this->memberOf($user), MembershipStatus::Active, '2024-01-01', '2024-12-31');
        $this->forgetRecorded();

        $period->update(['status' => MembershipStatus::Expired->value]);

        $this->assertNothingRecorded();
    }

    public function test_editing_the_dates_announces_the_years_before_and_after(): void
    {
        $user = UserModel::factory()->create();
        $period = $this->period($this->memberOf($user), MembershipStatus::Active, '2023-01-01', '2024-12-31');
        $this->forgetRecorded();

        $period->update(['start_date' => '2024-01-01', 'end_date' => '2025-12-31']);

        $this->assertRecorded($user, [2023, 2024, 2025]);
    }

    public function test_editing_only_the_notes_announces_nothing(): void
    {
        $period = $this->period($this->memberOf(UserModel::factory()->create()), MembershipStatus::Active, '2024-01-01', '2024-12-31');
        $this->forgetRecorded();

        $period->update(['notes' => 'Renewed by hand']);

        $this->assertNothingRecorded();
    }

    public function test_moving_a_period_to_another_record_announces_both_accounts(): void
    {
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();
        $period = $this->period($this->memberOf($first), MembershipStatus::Active, '2024-01-01', '2024-12-31');
        $target = $this->memberOf($second);
        $this->forgetRecorded();

        $period->update(['member_id' => $target->id]);

        $this->assertSame([$first->id.':2024', $second->id.':2024'], $this->recorded());
    }

    public function test_deleting_a_period_announces_its_years(): void
    {
        $user = UserModel::factory()->create();
        $period = $this->period($this->memberOf($user), MembershipStatus::Expired, '2024-01-01', '2025-12-31');
        $this->forgetRecorded();

        $period->delete();

        $this->assertRecorded($user, [2024, 2025]);
    }

    public function test_a_period_of_a_record_without_an_account_announces_nothing(): void
    {
        $member = $this->memberOf(null);

        $this->period($member, MembershipStatus::Active, '2024-01-01', '2024-12-31');

        $this->assertNothingRecorded();
    }

    public function test_recording_or_removing_a_payment_changes_no_year_and_announces_nothing(): void
    {
        $period = $this->period($this->memberOf(UserModel::factory()->create()), MembershipStatus::Active, '2024-01-01', '2024-12-31');
        $fee = MembershipFeeModel::create([
            'id' => (string) Str::uuid(),
            'membership_id' => $period->id,
            'amount' => '30.00',
            'currency' => 'EUR',
            'due_date' => '2024-01-31',
        ]);
        $this->forgetRecorded();

        $this->app->make(MembershipFeeServiceInterface::class)->recordPayment(new RecordPaymentDTO(
            feeId: $fee->id,
            paymentMethod: 'cash',
        ));
        $this->app->make(MembershipFeeServiceInterface::class)->delete($fee->id);

        $this->assertNothingRecorded();
    }

    public function test_linking_a_record_to_an_account_announces_its_years(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf(null);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2025-12-31');
        $this->forgetRecorded();

        $member->update(['user_id' => $user->id]);

        $this->assertRecorded($user, [2024, 2025]);
    }

    public function test_unlinking_a_record_announces_the_years_of_the_account_it_leaves(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $this->forgetRecorded();

        $member->update(['user_id' => null]);

        $this->assertRecorded($user, [2024]);
    }

    public function test_moving_a_record_to_another_account_announces_both(): void
    {
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();
        $member = $this->memberOf($first);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $this->forgetRecorded();

        $member->update(['user_id' => $second->id]);

        $this->assertSame([$first->id.':2024', $second->id.':2024'], $this->recorded());
    }

    public function test_changing_something_else_of_the_record_announces_nothing(): void
    {
        $member = $this->memberOf(UserModel::factory()->create());
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $this->forgetRecorded();

        $member->update(['phone' => '600000000', 'status' => 'inactive']);

        $this->assertNothingRecorded();
    }

    public function test_deleting_a_record_announces_its_years(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $this->forgetRecorded();

        $member->delete();

        $this->assertRecorded($user, [2024]);
    }

    public function test_restoring_a_record_announces_its_years(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $member->delete();
        $this->forgetRecorded();

        $member->restore();

        $this->assertRecorded($user, [2024]);
    }

    public function test_deleting_a_record_for_good_announces_the_years_its_periods_took_with_them(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2025-12-31');
        $this->forgetRecorded();

        $member->forceDelete();

        $this->assertRecorded($user, [2024, 2025]);
        $this->assertSame([], $this->app->make(MemberYearActivitySource::class)->facts($user->id.':2024'));
    }

    public function test_the_daily_command_announces_the_year_that_begins_today(): void
    {
        $user = UserModel::factory()->create();
        $this->period($this->memberOf($user), MembershipStatus::Active, '2026-09-01', '2027-06-30');
        $this->forgetRecorded();

        $this->travelTo(CarbonImmutable::parse('2027-01-01T00:30:00+01:00'));
        $this->announceYears();

        $this->assertRecorded($user, [2027]);
    }

    public function test_the_daily_command_announces_a_period_that_starts_today(): void
    {
        $user = UserModel::factory()->create();
        $this->period($this->memberOf($user), MembershipStatus::Active, '2026-09-01', '2027-06-30');
        $this->forgetRecorded();

        $this->travelTo(CarbonImmutable::parse('2026-09-01T00:30:00+02:00'));
        $this->announceYears();

        $this->assertRecorded($user, [2026]);
    }

    public function test_the_daily_command_is_quiet_on_an_ordinary_day(): void
    {
        $this->period($this->memberOf(UserModel::factory()->create()), MembershipStatus::Active, '2026-01-01', '2026-12-31');
        $this->forgetRecorded();

        $this->announceYears();

        $this->assertNothingRecorded();
    }

    public function test_the_daily_command_is_scheduled(): void
    {
        $events = collect($this->app->make(Schedule::class)->events());

        $this->assertTrue($events->contains(static fn ($event): bool => str_contains((string) $event->command, 'memberships:announce-years')));
    }

    /**
     * @param  list<int>  $years
     */
    private function assertRecorded(UserModel $user, array $years): void
    {
        $this->assertSame(array_map(static fn (int $year): string => $user->id.':'.$year, $years), $this->recorded());
    }

    private function assertNothingRecorded(): void
    {
        $this->assertSame([], $this->recorded());
    }

    /**
     * @return list<string> the subjects announced as `memberships.year`, without repeats, in order
     */
    private function recorded(): array
    {
        $ids = [];

        foreach ($this->events->dispatched(ActivityRecorded::class) as [$event]) {
            $this->assertSame('memberships.year', $event->sourceType);
            $ids[$event->subjectId] = true;
        }

        $ids = array_keys($ids);
        sort($ids);

        return $ids;
    }

    private function announceYears(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->registerCommand($this->app->make(AnnounceMemberYears::class));
        $kernel->call('memberships:announce-years');
    }

    private function forgetRecorded(): void
    {
        $this->events = Event::fake([ActivityRecorded::class]);
        $this->app->forgetInstance(ActivityPublisherInterface::class);
    }

    private function memberOf(?UserModel $user): MemberModel
    {
        $this->numbers++;

        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2020-%04d', $this->numbers),
            'first_name' => 'Ana',
            'last_name' => 'García',
            'member_type' => MemberType::Regular->value,
            'status' => 'active',
            'user_id' => $user?->id,
            'joined_at' => '2020-01-01 00:00:00',
        ]);
    }

    private function period(MemberModel $member, MembershipStatus $status, string $start, string $end): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'rolling',
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status->value,
        ]);
    }
}
