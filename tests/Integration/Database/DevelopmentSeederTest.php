<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Database;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\Memberships\Database\Seeders\DevelopmentSeeder;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Events\FeePaymentOverdue;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Domain\Events\MembershipExpiring;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Modules\ModuleTestCase;

final class DevelopmentSeederTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private function runSeeder(): void
    {
        require_once dirname(__DIR__, 3).'/database/seeders/DevelopmentSeeder.php';

        (new DevelopmentSeeder)->setContainer($this->app)->run();
    }

    public function test_it_writes_nothing_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->assertTrue($this->app->environment('production'));

        $this->runSeeder();

        $this->assertSame([0, 0, 0, 0, 0], $this->counts());
    }

    public function test_it_writes_nothing_in_the_testing_environment_so_suites_start_empty(): void
    {
        $this->runSeeder();

        $this->assertSame([0, 0, 0, 0, 0], $this->counts());
    }

    public function test_it_is_idempotent(): void
    {
        $this->app['env'] = 'local';

        $this->runSeeder();
        $counts = $this->counts();

        $this->runSeeder();

        $this->assertSame($counts, $this->counts());
        $this->assertGreaterThan(0, $counts[1]);
    }

    public function test_it_seeds_every_scenario_without_expiry_overdue_or_mail_events(): void
    {
        $this->app['env'] = 'local';
        Mail::fake();
        Notification::fake();
        Event::fake([MembershipExpiring::class, MembershipExpired::class, FeePaymentOverdue::class]);

        $this->runSeeder();

        $this->assertSame(1, MembershipModel::where('status', MembershipStatus::Expired->value)->count());
        $this->assertSame(1, MembershipModel::where('status', MembershipStatus::Active->value)
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count());
        $this->assertSame(1, MembershipFeeModel::whereNull('paid_at')->where('due_date', '<', now()->toDateString())->count());
        $this->assertGreaterThanOrEqual(1, MembershipFeeModel::whereNotNull('paid_at')->count());
        $this->assertSame(1, MemberModel::where('member_type', MemberType::Honorary->value)->count());
        $this->assertSame(0, FeeStructureModel::where('member_type', MemberType::Honorary->value)->count());
        $this->assertSame(4, MemberModel::whereIn('user_id', UserModel::where('email', 'like', '%@example.local')->pluck('id'))->count());

        Event::assertNotDispatched(MembershipExpiring::class);
        Event::assertNotDispatched(MembershipExpired::class);
        Event::assertNotDispatched(FeePaymentOverdue::class);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    /**
     * @return array{int, int, int, int, int}
     */
    private function counts(): array
    {
        return [
            FeeStructureModel::count(),
            MemberModel::count(),
            MembershipModel::count(),
            MembershipFeeModel::count(),
            UserModel::where('email', 'like', '%@example.local')->count(),
        ];
    }
}
