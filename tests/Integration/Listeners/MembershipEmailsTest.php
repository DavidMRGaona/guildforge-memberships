<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Listeners;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;
use Modules\Memberships\Notifications\MembershipExpiredNotification;
use Modules\Memberships\Notifications\MembershipExpiringNotification;
use Modules\Memberships\Notifications\PaymentOverdueNotification;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * Expired-membership and overdue-fee emails exist but stay off until an admin turns
 * them on in the module settings (send_expired_email, send_overdue_email).
 */
final class MembershipEmailsTest extends ModuleTestCase
{
    use UsesUtcStorage;

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    private MemberModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        Notification::fake();

        $this->user = UserModel::factory()->create(['email' => 'john.cuenta@example.test']);
        $this->member = $this->member('regular', $this->user->id);

        $this->app->make(Kernel::class)->registerCommand($this->app->make(ProcessExpiredMemberships::class));
    }

    public function test_both_emails_are_off_by_default(): void
    {
        $reader = $this->app->make(MembershipSettingsReader::class);

        $this->assertFalse($reader->expiredEmailEnabled());
        $this->assertFalse($reader->overdueEmailEnabled());
    }

    public function test_expired_membership_sends_nothing_while_the_setting_is_off(): void
    {
        $this->membership('active')->update(['status' => 'expired']);

        Notification::assertNothingSent();
    }

    public function test_expired_membership_notifies_the_linked_user_when_enabled(): void
    {
        config()->set('modules.settings.memberships.send_expired_email', true);

        $this->membership('active', end: now()->subDay())->update(['status' => 'expired']);

        Notification::assertSentTo($this->user, MembershipExpiredNotification::class);
    }

    public function test_expired_membership_of_a_member_without_account_goes_to_the_member_email(): void
    {
        config()->set('modules.settings.memberships.send_expired_email', true);
        $member = $this->member('regular', null, 'sin.cuenta@example.test');

        $this->membership('active', $member)->update(['status' => 'expired']);

        Notification::assertSentOnDemand(
            MembershipExpiredNotification::class,
            fn (MembershipExpiredNotification $n, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'sin.cuenta@example.test',
        );
    }

    public function test_expired_membership_of_a_permanent_member_sends_nothing(): void
    {
        config()->set('modules.settings.memberships.send_expired_email', true);
        config()->set('modules.settings.memberships.permanent_member_types', ['honorary']);
        $member = $this->member('honorary', null, 'honoraria@example.test');

        $this->membership('active', $member)->update(['status' => 'expired']);

        Notification::assertNothingSent();
    }

    public function test_expired_membership_of_a_member_who_already_renewed_sends_nothing(): void
    {
        config()->set('modules.settings.memberships.send_expired_email', true);
        $old = $this->membership('active', end: now()->subDay(), start: now()->subYear());
        $this->membership('pending', end: now()->addYear(), start: now());

        $old->update(['status' => 'expired']);

        Notification::assertNothingSent();
    }

    public function test_expired_membership_of_an_inactive_member_sends_nothing(): void
    {
        config()->set('modules.settings.memberships.send_expired_email', true);
        $member = $this->member('regular', null, 'baja@example.test', status: 'inactive');

        $this->membership('active', $member)->update(['status' => 'expired']);

        Notification::assertNothingSent();
    }

    public function test_overdue_fee_of_an_expelled_member_sends_nothing(): void
    {
        config()->set('modules.settings.memberships.send_overdue_email', true);
        $member = $this->member('regular', null, 'expulsado@example.test', status: 'expelled');
        $this->fee($this->membership('active', $member), dueDaysAgo: 10);

        $this->processExpired();

        Notification::assertNothingSent();
    }

    public function test_overdue_fee_of_a_cancelled_membership_sends_nothing(): void
    {
        config()->set('modules.settings.memberships.send_overdue_email', true);
        $this->fee($this->membership('cancelled'), dueDaysAgo: 10);

        $this->processExpired();

        Notification::assertNotSentTo($this->user, PaymentOverdueNotification::class);
    }

    public function test_overdue_fee_sends_nothing_while_the_setting_is_off(): void
    {
        $this->fee($this->membership('active'), dueDaysAgo: 10);

        $this->processExpired();

        Notification::assertNotSentTo($this->user, PaymentOverdueNotification::class);
    }

    public function test_overdue_fee_notifies_the_member_with_amount_and_delay_when_enabled(): void
    {
        config()->set('modules.settings.memberships.send_overdue_email', true);
        $this->fee($this->membership('active'), dueDaysAgo: 10, amount: '25.00');

        $this->processExpired();

        $this->assertOverdueNoticeSays('25,00 €', '10 días');
    }

    /**
     * Right after midnight in the display zone, UTC is still on the day before: a due date
     * taken from the storage day made the notice read «11 días» (seen as a random failure).
     */
    public function test_overdue_delay_counts_display_days_right_after_midnight(): void
    {
        $this->useUtcStorage();
        $displayZone = app(TimezoneServiceInterface::class)->displayTimezone();
        $this->travelTo(CarbonImmutable::parse('2026-03-11 00:30:00', $displayZone));
        config()->set('modules.settings.memberships.send_overdue_email', true);
        $this->fee($this->membership('active'), dueDaysAgo: 10, amount: '25.00');

        $this->processExpired();

        $this->assertOverdueNoticeSays('25,00 €', '10 días');
    }

    public function test_paid_fee_is_never_reported(): void
    {
        config()->set('modules.settings.memberships.send_overdue_email', true);
        $this->fee($this->membership('active'), dueDaysAgo: 10, paid: true);

        $this->processExpired();

        Notification::assertNotSentTo($this->user, PaymentOverdueNotification::class);
    }

    public function test_membership_emails_to_an_account_link_to_its_member_tab(): void
    {
        foreach ($this->notices() as $notification) {
            $message = $notification->toMail($this->user);

            $this->assertSame(route('profile.show', ['tab' => 'memberships']), $message->actionUrl, $notification::class);
            $this->assertSame('Ver tu situación de socio', $message->actionText, $notification::class);
        }
    }

    public function test_membership_emails_to_a_member_without_account_link_to_the_association_page(): void
    {
        $address = (new AnonymousNotifiable)->route('mail', 'sin.cuenta@example.test');

        foreach ($this->notices() as $notification) {
            $message = $notification->toMail($address);

            $this->assertSame(route('about'), $message->actionUrl, $notification::class);
            $this->assertSame('Contactar con la asociación', $message->actionText, $notification::class);
        }
    }

    /**
     * @return list<MembershipExpiringNotification|MembershipExpiredNotification|PaymentOverdueNotification>
     */
    private function notices(): array
    {
        $date = new \DateTimeImmutable('2026-12-31');

        return [
            new MembershipExpiringNotification('John', $date, 7),
            new MembershipExpiredNotification('John', $date),
            new PaymentOverdueNotification('John', '25,00 €', $date, 3),
        ];
    }

    private function member(string $type, ?string $userId, string $email = 'john@example.test', string $status = 'active'): MemberModel
    {
        static $number = 1;

        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2026-%04d', $number++),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $email,
            'member_type' => $type,
            'status' => $status,
            'user_id' => $userId,
            'joined_at' => now(),
        ]);
    }

    private function membership(string $status, ?MemberModel $member = null, ?\DateTimeInterface $end = null, ?\DateTimeInterface $start = null): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => ($member ?? $this->member)->id,
            'period_type' => 'calendar_year',
            'start_date' => $start ?? now()->subYear()->startOfYear(),
            'end_date' => $end ?? now()->addYear()->endOfYear(),
            'status' => $status,
        ]);
    }

    private function fee(MembershipModel $membership, int $dueDaysAgo, string $amount = '30.00', bool $paid = false): MembershipFeeModel
    {
        return MembershipFeeModel::create([
            'id' => (string) Str::uuid(),
            'membership_id' => $membership->id,
            'amount' => $amount,
            'currency' => 'EUR',
            // A calendar day, counted back from today in the display zone as the listener does
            'due_date' => CalendarDate::today()->modify("-{$dueDaysAgo} days")->format('Y-m-d'),
            'paid_at' => $paid ? now()->subDays($dueDaysAgo + 1) : null,
        ]);
    }

    private function assertOverdueNoticeSays(string ...$fragments): void
    {
        $intro = null;
        Notification::assertSentTo($this->user, PaymentOverdueNotification::class, function (PaymentOverdueNotification $n) use (&$intro): bool {
            $intro = implode(' ', $n->toMail($this->user)->introLines);

            return true;
        });

        foreach ($fragments as $fragment) {
            $this->assertStringContainsString($fragment, (string) $intro);
        }
    }

    private function processExpired(): void
    {
        $command = $this->artisan('memberships:process-expired');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful();
    }
}
