<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Listeners;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Domain\Events\MembershipActivated;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Modules\ModuleTestCase;

final class MemberRoleSyncTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    private MemberModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);

        config()->set('modules.settings.memberships.enable_role_assignment', true);
        config()->set('modules.settings.memberships.member_role_name', 'member');

        $this->user = UserModel::factory()->create();
        $this->member = MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => '2026-0001',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.test',
            'member_type' => 'regular',
            'status' => 'active',
            'user_id' => $this->user->id,
            'joined_at' => now(),
        ]);

        // The module is enabled after the console kernel has loaded its commands
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ProcessExpiredMemberships::class));
    }

    public function test_activating_a_membership_grants_the_member_role_when_enabled(): void
    {
        $membership = $this->membership('pending');

        // Warm the role cache: the grant must clear it
        $this->assertFalse($this->authorization()->hasRole($this->user, 'member'));

        $membership->update(['status' => 'active']);

        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_creating_an_active_membership_grants_the_member_role(): void
    {
        $this->membership('active');

        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_no_role_is_granted_when_role_assignment_is_disabled(): void
    {
        config()->set('modules.settings.memberships.enable_role_assignment', false);

        $this->membership('active');

        $this->assertFalse($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_the_role_name_saved_in_the_panel_is_used(): void
    {
        RoleModel::firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        config()->set('modules.settings.memberships.member_role_name', 'socio');

        $this->membership('active');

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertFalse($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_activation_is_dispatched_once(): void
    {
        Event::fake([MembershipActivated::class]);

        $membership = $this->membership('pending');
        $membership->update(['status' => 'active']);
        $membership->update(['notes' => 'Pagada en efectivo']);

        Event::assertDispatchedTimes(MembershipActivated::class, 1);
    }

    public function test_expiring_the_last_membership_revokes_the_role(): void
    {
        $this->membership('active', now()->subYear()->startOfYear(), now()->subYear()->endOfYear());
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));

        $this->assertSame(0, $this->app->make(Kernel::class)->call('memberships:process-expired'));

        $this->assertFalse($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_expiry_by_the_command_is_dispatched_once(): void
    {
        Event::fake([MembershipExpired::class]);
        $this->membership('active', now()->subYear()->startOfYear(), now()->subYear()->endOfYear());

        $this->assertSame(0, $this->app->make(Kernel::class)->call('memberships:process-expired'));

        Event::assertDispatchedTimes(MembershipExpired::class, 1);
    }

    public function test_marking_a_membership_as_expired_in_the_panel_revokes_the_role(): void
    {
        $membership = $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));

        $membership->update(['status' => 'expired']);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_the_role_is_kept_while_another_membership_is_active(): void
    {
        $this->membership('active', now()->startOfYear(), now()->endOfYear());
        $expiring = $this->membership('active', now()->subYear()->startOfYear(), now()->subYear()->endOfYear());

        $expiring->update(['status' => 'expired']);

        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
    }

    private function membership(string $status, ?\DateTimeInterface $start = null, ?\DateTimeInterface $end = null): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $this->member->id,
            'period_type' => 'calendar_year',
            'start_date' => $start ?? now()->startOfYear(),
            'end_date' => $end ?? now()->endOfYear(),
            'status' => $status,
        ]);
    }

    private function authorization(): AuthorizationServiceInterface
    {
        return $this->app->make(AuthorizationServiceInterface::class);
    }
}
