<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Listeners;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\DTOs\MailPreferenceSectionDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\MembershipsServiceProvider;
use Modules\Memberships\Notifications\MembershipExpiredNotification;
use Modules\Memberships\Notifications\MembershipExpiringNotification;
use Modules\Memberships\Notifications\PaymentOverdueNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every mail the module sends to an account belongs to a registered category, so people can
 * choose what they receive from their profile (core mail categories).
 */
final class MailCategoriesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    /**
     * @return array<string, array{0: Notification&HasMailCategory, 1: string}>
     */
    public static function notifications(): array
    {
        $date = new DateTimeImmutable('2026-12-31');

        return [
            'expiring' => [new MembershipExpiringNotification('Ana', $date, 30), MembershipsServiceProvider::MAIL_EXPIRY_NOTICES],
            'expired' => [new MembershipExpiredNotification('Ana', $date), MembershipsServiceProvider::MAIL_FEES],
            'overdue' => [new PaymentOverdueNotification('Ana', '10,00 €', $date, 5), MembershipsServiceProvider::MAIL_FEES],
        ];
    }

    #[DataProvider('notifications')]
    public function test_every_notification_has_a_registered_category(Notification $notification, string $expectedKey): void
    {
        $this->assertInstanceOf(HasMailCategory::class, $notification);
        $this->assertSame($expectedKey, $notification->mailCategory());

        $category = $this->app->make(MailCategoryRegistryInterface::class)->find($expectedKey);

        $this->assertNotNull($category);
        $this->assertSame($category, $this->app->make(MailCategoryRegistryInterface::class)->forNotification($notification));
    }

    public function test_expiry_notices_are_optional_and_fees_are_mandatory(): void
    {
        $registry = $this->app->make(MailCategoryRegistryInterface::class);

        $this->assertFalse($registry->find(MembershipsServiceProvider::MAIL_EXPIRY_NOTICES)?->isMandatory());
        $this->assertTrue($registry->find(MembershipsServiceProvider::MAIL_FEES)?->isMandatory());
    }

    public function test_the_notification_files_are_all_covered_by_this_test(): void
    {
        $files = glob(dirname(__DIR__, 3).'/src/Notifications/*.php') ?: [];

        $this->assertCount(count(self::notifications()), $files);
    }

    public function test_an_optional_category_turned_off_is_not_sent(): void
    {
        $user = UserModel::factory()->create();
        $this->member($user->id);
        $this->app->make(MailPreferenceServiceInterface::class)
            ->update($user, [MembershipsServiceProvider::MAIL_EXPIRY_NOTICES => false]);
        $before = $this->sentCount();

        $user->notify(new MembershipExpiringNotification('Ana', new DateTimeImmutable('2026-12-31'), 30));

        $this->assertSame($before, $this->sentCount());
    }

    public function test_an_optional_category_left_on_is_sent(): void
    {
        $user = UserModel::factory()->create();
        $before = $this->sentCount();

        $user->notify(new MembershipExpiringNotification('Ana', new DateTimeImmutable('2026-12-31'), 30));

        $this->assertSame($before + 1, $this->sentCount());
    }

    public function test_the_mandatory_category_is_sent_even_with_everything_optional_off(): void
    {
        $user = UserModel::factory()->create();
        $this->member($user->id);
        $this->app->make(MailPreferenceServiceInterface::class)
            ->update($user, [MembershipsServiceProvider::MAIL_EXPIRY_NOTICES => false]);
        $date = new DateTimeImmutable('2026-12-31');
        $before = $this->sentCount();

        $user->notify(new MembershipExpiredNotification('Ana', $date));
        $user->notify(new PaymentOverdueNotification('Ana', '10,00 €', $date, 5));

        $this->assertSame($before + 2, $this->sentCount());
    }

    public function test_the_categories_are_only_listed_for_accounts_with_a_member_record(): void
    {
        $service = $this->app->make(MailPreferenceServiceInterface::class);
        $user = UserModel::factory()->create();

        $this->assertSame([], $this->listedKeys($service->sectionsFor($user)));

        $this->member($user->id);

        $this->assertSame(
            [MembershipsServiceProvider::MAIL_EXPIRY_NOTICES, MembershipsServiceProvider::MAIL_FEES],
            $this->listedKeys($service->sectionsFor($user)),
        );
    }

    public function test_the_section_names_the_persons_own_membership_not_the_administration(): void
    {
        $service = $this->app->make(MailPreferenceServiceInterface::class);
        $user = UserModel::factory()->create();
        $this->member($user->id);

        $labels = array_map(
            static fn (MailPreferenceSectionDTO $section): string => $section->label,
            $service->sectionsFor($user),
        );

        $this->assertContains('Tu membresía', $labels);
        $this->assertNotContains('Gestión de socios', $labels);
    }

    /**
     * @param  list<MailPreferenceSectionDTO>  $sections
     * @return list<string>
     */
    private function listedKeys(array $sections): array
    {
        $keys = [];
        foreach ($sections as $section) {
            foreach ($section->categories as $category) {
                if (str_starts_with($category->key, 'memberships.')) {
                    $keys[] = $category->key;
                }
            }
        }

        return $keys;
    }

    private function sentCount(): int
    {
        $transport = $this->app->make('mailer')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return count($transport->messages());
    }

    private function member(string $userId): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => '2026-0001',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.test',
            'member_type' => 'regular',
            'status' => 'active',
            'user_id' => $userId,
            'joined_at' => now(),
        ]);
    }
}
