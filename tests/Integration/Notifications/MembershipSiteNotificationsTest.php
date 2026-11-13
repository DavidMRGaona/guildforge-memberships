<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Notifications;

use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\MembershipsServiceProvider;
use Modules\Memberships\Notifications\MembershipExpiredNotification;
use Modules\Memberships\Notifications\MembershipExpiringNotification;
use Modules\Memberships\Notifications\PaymentOverdueNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The membership notices also go to the bell of the linked account (core#491). Members without
 * an account keep the mail only: the centre never stores a notice for an address on its own.
 */
final class MembershipSiteNotificationsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
    }

    /**
     * @return array<string, array{0: Notification&HasSiteNotification, 1: string, 2: string}>
     */
    public static function notifications(): array
    {
        $date = new DateTimeImmutable('2026-12-31');

        return [
            'expiring' => [
                new MembershipExpiringNotification('Ana', $date, 30),
                'Tu membresía expira pronto',
                'id-card',
            ],
            'expired' => [
                new MembershipExpiredNotification('Ana', $date),
                'Tu membresía ha expirado',
                'id-card',
            ],
            'overdue' => [
                new PaymentOverdueNotification('Ana', '10,00 €', $date, 5),
                'Tienes una cuota pendiente de pago',
                'credit-card',
            ],
        ];
    }

    public function test_both_categories_show_in_the_bell(): void
    {
        $registry = $this->app->make(MailCategoryRegistryInterface::class);

        $this->assertTrue($registry->find(MembershipsServiceProvider::MAIL_EXPIRY_NOTICES)?->site);
        $this->assertTrue($registry->find(MembershipsServiceProvider::MAIL_FEES)?->site);
    }

    #[DataProvider('notifications')]
    public function test_each_notification_goes_to_the_site_channel(MembershipExpiringNotification|MembershipExpiredNotification|PaymentOverdueNotification $notification, string $title, string $icon): void
    {
        $this->assertContains(HasSiteNotification::CHANNEL, $notification->via(UserModel::factory()->make()));
        $this->assertContains('mail', $notification->via(UserModel::factory()->make()));
    }

    #[DataProvider('notifications')]
    public function test_the_bell_notice_has_title_icon_and_relative_link_to_about(MembershipExpiringNotification|MembershipExpiredNotification|PaymentOverdueNotification $notification, string $title, string $icon): void
    {
        $dto = $notification->toSiteNotification(UserModel::factory()->make());

        $this->assertSame($title, $dto->title);
        $this->assertSame($icon, $dto->icon);
        $this->assertSame(route('about', absolute: false), $dto->url);
        $this->assertNull($dto->groupKey);
        $this->assertNull($dto->actorId);
    }

    public function test_the_linked_account_gets_the_bell_notice_in_its_category(): void
    {
        $user = UserModel::factory()->create();
        $this->member('regular', $user->id);

        $user->notify(new MembershipExpiringNotification('Ana', new DateTimeImmutable('2026-12-31'), 30));

        $this->assertDatabaseHas('site_notifications', [
            'user_id' => $user->id,
            'category' => MembershipsServiceProvider::MAIL_EXPIRY_NOTICES,
            'title' => 'Tu membresía expira pronto',
            'url' => route('about', absolute: false),
        ]);
    }

    public function test_a_member_without_account_gets_no_bell_notice(): void
    {
        NotificationFacade::route('mail', 'sin.cuenta@example.test')
            ->notify(new MembershipExpiringNotification('Ana', new DateTimeImmutable('2026-12-31'), 30));

        $this->assertDatabaseCount('site_notifications', 0);
    }

    private function member(string $type, ?string $userId): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => '2026-9001',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.test',
            'member_type' => $type,
            'status' => 'active',
            'user_id' => $userId,
            'joined_at' => now(),
        ]);
    }
}
