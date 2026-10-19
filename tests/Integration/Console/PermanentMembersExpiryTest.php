<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Console;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Events\FeePaymentOverdue;
use Modules\Memberships\Domain\Events\MembershipExpiring;
use Modules\Memberships\Domain\Repositories\MembershipFeeRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Notifications\MembershipExpiringNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Members of a permanent type (honorary by default) are left out of everything about
 * expiry and payment.
 */
final class PermanentMembersExpiryTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private int $memberCount = 0;

    public function test_a_permanent_member_gets_no_expiry_warning(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create();
        $membership = $this->membership($this->member(MemberType::Honorary, $user), now()->addDays(10));

        $this->processExpired();

        Notification::assertNotSentTo($user, MembershipExpiringNotification::class);
        $this->assertStringNotContainsString('EXPIRING_NOTIFIED', (string) $membership->fresh()?->notes);
    }

    public function test_a_regular_member_still_gets_the_expiry_warning(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create();
        $membership = $this->membership($this->member(MemberType::Regular, $user), now()->addDays(10));

        $this->processExpired();

        Notification::assertSentTo($user, MembershipExpiringNotification::class);
        $this->assertStringContainsString('EXPIRING_NOTIFIED', (string) $membership->fresh()?->notes);
    }

    public function test_no_expiring_event_is_dispatched_for_a_permanent_member(): void
    {
        Event::fake([MembershipExpiring::class]);
        $regular = $this->membership($this->member(MemberType::Regular), now()->addDays(10));
        $this->membership($this->member(MemberType::Honorary), now()->addDays(10));

        $this->processExpired();

        Event::assertDispatchedTimes(MembershipExpiring::class, 1);
        Event::assertDispatched(MembershipExpiring::class, fn (MembershipExpiring $event): bool => $event->membershipId === $regular->id);
    }

    public function test_the_expiry_warning_listener_ignores_permanent_members(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create();
        $membership = $this->membership($this->member(MemberType::Honorary, $user), now()->addDays(10));
        $entity = $this->app->make(MembershipRepositoryInterface::class)->find(new MembershipId($membership->id));
        $this->assertNotNull($entity);

        event(MembershipExpiring::create($entity, 10));

        Notification::assertNothingSent();
    }

    public function test_a_permanent_member_fee_is_never_reported_overdue(): void
    {
        Event::fake([FeePaymentOverdue::class]);
        $regularFee = $this->overdueFee($this->membership($this->member(MemberType::Regular), now()->endOfYear()));
        $honoraryFee = $this->overdueFee($this->membership($this->member(MemberType::Honorary), now()->endOfYear()));

        $this->processExpired();

        Event::assertDispatchedTimes(FeePaymentOverdue::class, 1);
        Event::assertDispatched(FeePaymentOverdue::class, fn (FeePaymentOverdue $event): bool => $event->feeId === $regularFee->id);
        $this->assertStringNotContainsString('OVERDUE_NOTIFIED', (string) $honoraryFee->fresh()?->notes);
    }

    public function test_a_type_left_out_of_the_permanent_types_is_warned_again(): void
    {
        config()->set('modules.settings.memberships.permanent_member_types', []);
        Event::fake([MembershipExpiring::class, FeePaymentOverdue::class]);
        $membership = $this->membership($this->member(MemberType::Honorary), now()->addDays(10));
        $this->overdueFee($membership);

        $this->processExpired();

        Event::assertDispatchedTimes(MembershipExpiring::class, 1);
        Event::assertDispatchedTimes(FeePaymentOverdue::class, 1);
    }

    public function test_an_old_membership_of_a_permanent_member_expires_without_taking_the_role(): void
    {
        config()->set('modules.settings.memberships.enable_role_assignment', true);
        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $user = UserModel::factory()->create();
        $membership = $this->membership(
            $this->member(MemberType::Honorary, $user),
            now()->subYear()->endOfYear(),
            now()->subYear()->startOfYear(),
        );
        $this->assertTrue($this->authorization()->hasRole($user, 'socio'));

        $this->processExpired();

        $this->assertSame('expired', $membership->fresh()?->status->value);
        $this->assertTrue($this->authorization()->hasRole($user, 'socio'));
    }

    public function test_the_unpaid_count_leaves_permanent_members_out_when_asked(): void
    {
        $this->overdueFee($this->membership($this->member(MemberType::Regular), now()->endOfYear()));
        $this->overdueFee($this->membership($this->member(MemberType::Honorary), now()->endOfYear()));
        $fees = $this->app->make(MembershipFeeRepositoryInterface::class);

        $this->assertSame(2, $fees->countUnpaid());
        $this->assertSame(1, $fees->countUnpaid([MemberType::Honorary]));
        $this->assertCount(1, $fees->getOverdueFees([MemberType::Honorary]));
    }

    public function test_a_deleted_permanent_member_stays_out(): void
    {
        $member = $this->member(MemberType::Honorary);
        $this->overdueFee($this->membership($member, now()->addDays(10)));
        $member->delete();
        $memberships = $this->app->make(MembershipRepositoryInterface::class);
        $fees = $this->app->make(MembershipFeeRepositoryInterface::class);

        $this->assertSame([], $memberships->getExpiringMemberships(30, [MemberType::Honorary]));
        $this->assertCount(1, $memberships->getExpiringMemberships(30));
        $this->assertSame(0, $fees->countUnpaid([MemberType::Honorary]));
    }

    private function processExpired(): void
    {
        // The module is enabled after the console kernel has loaded its commands; built here
        // so the command gets the event dispatcher faked by the test
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ProcessExpiredMemberships::class));

        $this->assertSame(0, $this->app->make(Kernel::class)->call('memberships:process-expired'));
    }

    private function member(MemberType $type, ?UserModel $user = null): MemberModel
    {
        $this->memberCount++;

        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2026-%04d', $this->memberCount),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => "ana{$this->memberCount}@example.test",
            'member_type' => $type->value,
            'status' => 'active',
            'user_id' => $user?->id,
            'joined_at' => now(),
        ]);
    }

    private function membership(MemberModel $member, \DateTimeInterface $end, ?\DateTimeInterface $start = null): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => $start ?? now()->startOfYear(),
            'end_date' => $end,
            'status' => 'active',
        ]);
    }

    private function overdueFee(MembershipModel $membership): MembershipFeeModel
    {
        return MembershipFeeModel::create([
            'id' => (string) Str::uuid(),
            'membership_id' => $membership->id,
            'amount' => 30,
            'currency' => 'EUR',
            'due_date' => (new DateTimeImmutable('-5 days'))->format('Y-m-d'),
        ]);
    }

    private function authorization(): AuthorizationServiceInterface
    {
        return $this->app->make(AuthorizationServiceInterface::class);
    }
}
