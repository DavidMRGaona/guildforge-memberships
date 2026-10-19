<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;
use Modules\Memberships\Notifications\MembershipExpiredNotification;
use Modules\Memberships\Notifications\MembershipExpiringNotification;
use Modules\Memberships\Notifications\PaymentOverdueNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Expired-membership and overdue-fee emails exist but stay off until an admin turns
 * them on in the module settings (send_expired_email, send_overdue_email).
 */
final class MembershipEmailsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    private MemberModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        Notification::fake();

        $this->user = UserModel::factory()->create(['email' => 'ana.cuenta@example.test']);
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

    public function test_overdue_fee_sends_nothing_while_the_setting_is_off(): void
    {
        $this->fee($this->membership('active'), dueDaysAgo: 10);

        $this->artisan('memberships:process-expired')->assertSuccessful();

        Notification::assertNotSentTo($this->user, PaymentOverdueNotification::class);
    }

    public function test_overdue_fee_notifies_the_member_with_amount_and_delay_when_enabled(): void
    {
        config()->set('modules.settings.memberships.send_overdue_email', true);
        $this->fee($this->membership('active'), dueDaysAgo: 10, amount: '25.00');

        $this->artisan('memberships:process-expired')->assertSuccessful();

        Notification::assertSentTo($this->user, PaymentOverdueNotification::class, function (PaymentOverdueNotification $n): bool {
            $text = implode(' ', $n->toMail($this->user)->introLines);

            return str_contains($text, '25,00 €') && str_contains($text, '10 días');
        });
    }

    public function test_paid_fee_is_never_reported(): void
    {
        config()->set('modules.settings.memberships.send_overdue_email', true);
        $this->fee($this->membership('active'), dueDaysAgo: 10, paid: true);

        $this->artisan('memberships:process-expired')->assertSuccessful();

        Notification::assertNotSentTo($this->user, PaymentOverdueNotification::class);
    }

    public function test_membership_emails_link_to_the_association_contact_page(): void
    {
        $date = new \DateTimeImmutable('2026-12-31');
        $notifications = [
            new MembershipExpiringNotification('Ana', $date, 7),
            new MembershipExpiredNotification('Ana', $date),
            new PaymentOverdueNotification('Ana', '25,00 €', $date, 3),
        ];

        foreach ($notifications as $notification) {
            $message = $notification->toMail($this->user);

            $this->assertSame(route('about'), $message->actionUrl, $notification::class);
            $this->assertSame('Contactar con la asociación', $message->actionText, $notification::class);
        }
    }

    private function member(string $type, ?string $userId, string $email = 'ana@example.test'): MemberModel
    {
        static $number = 1;

        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2026-%04d', $number++),
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => $email,
            'member_type' => $type,
            'status' => 'active',
            'user_id' => $userId,
            'joined_at' => now(),
        ]);
    }

    private function membership(string $status, ?MemberModel $member = null, ?\DateTimeInterface $end = null): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => ($member ?? $this->member)->id,
            'period_type' => 'calendar_year',
            'start_date' => now()->subYear()->startOfYear(),
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
            'due_date' => now()->subDays($dueDaysAgo)->startOfDay(),
            'paid_at' => $paid ? now()->subDays($dueDaysAgo + 1) : null,
        ]);
    }
}
