<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Listeners;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Memberships\Application\Services\MemberServiceInterface;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Domain\Events\MembershipActivated;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The association member role ("socio" by default, chosen in the core site settings) is
 * held by the users linked to an active member with an active membership or of a permanent
 * type. "member", the role of every registered user, is never touched.
 */
final class MemberRoleSyncTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    private MemberModel $member;

    private int $memberCount = 1;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        RoleModel::firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);

        config()->set('modules.settings.memberships.enable_role_assignment', true);
        config()->set('modules.settings.memberships.permanent_member_types', ['honorary']);

        $this->user = UserModel::factory()->create();
        // Every registered user holds the base role
        $this->authorization()->assignRole($this->user, 'member');
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

    public function test_activating_a_membership_grants_the_association_role_and_leaves_member_alone(): void
    {
        $membership = $this->membership('pending');

        // Warm the role cache: the grant must clear it
        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));

        $membership->update(['status' => 'active']);

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_creating_an_active_membership_grants_the_association_role(): void
    {
        $this->membership('active');

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_a_regular_member_without_a_membership_does_not_hold_the_role(): void
    {
        $this->membership('pending');
        $this->member->update(['notes' => 'Sin cuota']);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_no_role_is_granted_when_role_assignment_is_disabled(): void
    {
        config()->set('modules.settings.memberships.enable_role_assignment', false);

        $this->membership('active');
        $this->member->update(['member_type' => 'honorary']);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_activation_is_dispatched_once(): void
    {
        Event::fake([MembershipActivated::class]);

        $membership = $this->membership('pending');
        $membership->update(['status' => 'active']);
        $membership->update(['notes' => 'Pagada en efectivo']);

        Event::assertDispatchedTimes(MembershipActivated::class, 1);
    }

    public function test_expiring_the_last_membership_revokes_the_association_role_only(): void
    {
        $this->membership('active', now()->subYear()->startOfYear(), now()->subYear()->endOfYear());
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $this->assertSame(0, $this->app->make(Kernel::class)->call('memberships:process-expired'));

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
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
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $membership->update(['status' => 'expired']);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_the_role_is_kept_while_another_membership_is_active(): void
    {
        $this->membership('active', now()->startOfYear(), now()->endOfYear());
        $expiring = $this->membership('active', now()->subYear()->startOfYear(), now()->subYear()->endOfYear());

        $expiring->update(['status' => 'expired']);

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_cancelling_an_active_membership_revokes_the_role(): void
    {
        $membership = $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $membership->update(['status' => 'cancelled']);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_moving_an_active_membership_back_to_pending_revokes_the_role(): void
    {
        $membership = $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $membership->update(['status' => 'pending']);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_deleting_an_active_membership_revokes_the_role(): void
    {
        $membership = $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $membership->delete();

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_deleting_an_active_membership_through_the_repository_revokes_the_role(): void
    {
        $membership = $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $this->app->make(MembershipRepositoryInterface::class)->delete(new MembershipId($membership->id));

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    #[DataProvider('statusesWithoutTheRole')]
    public function test_a_member_whose_record_is_not_active_loses_the_role_despite_an_active_membership(string $status): void
    {
        $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $this->member->update(['status' => $status]);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));

        $this->member->update(['status' => 'active']);

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusesWithoutTheRole(): array
    {
        return [
            'suspended' => ['suspended'],
            'expelled' => ['expelled'],
            'inactive' => ['inactive'],
        ];
    }

    public function test_suspending_through_the_member_service_revokes_the_role(): void
    {
        $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $this->app->make(MemberServiceInterface::class)->changeStatus($this->member->id, 'suspended');

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_a_new_honorary_member_holds_the_role_without_a_membership(): void
    {
        $user = UserModel::factory()->create();

        $this->linkedMember($user, 'honorary');

        $this->assertTrue($this->authorization()->hasRole($user, 'socio'));
    }

    public function test_changing_to_a_permanent_type_grants_the_role_and_back_revokes_it(): void
    {
        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));

        $this->member->update(['member_type' => 'honorary']);
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $this->member->update(['member_type' => 'regular']);
        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_a_suspended_honorary_member_loses_the_role(): void
    {
        $this->member->update(['member_type' => 'honorary']);
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $this->member->update(['status' => 'suspended']);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_the_role_chosen_in_the_core_is_granted(): void
    {
        RoleModel::firstOrCreate(['name' => 'junta'], ['display_name' => 'Junta']);
        $this->configureCoreRole('junta');

        $this->membership('active');

        $this->assertTrue($this->authorization()->hasRole($this->user, 'junta'));
        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_no_role_changes_while_the_core_setting_holds_a_base_role(): void
    {
        $log = Log::spy();
        $this->authorization()->assignRole($this->user, 'socio');
        RoleModel::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->configureCoreRole('admin');

        $this->membership('active');
        $this->member->update(['status' => 'suspended']);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'admin'));
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
        $log->shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_starts_with($message, 'Memberships:'))->atLeast()->once();
    }

    public function test_no_role_changes_while_the_core_role_does_not_exist(): void
    {
        $log = Log::spy();
        $this->configureCoreRole('junta');

        $this->membership('active');

        $this->assertFalse(RoleModel::query()->where('name', 'junta')->exists());
        $this->assertSame(['member'], $this->authorization()->getRoles($this->user->fresh() ?? $this->user));
        $log->shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_starts_with($message, 'Memberships:'))->atLeast()->once();
    }

    #[DataProvider('baseRoles')]
    public function test_a_base_role_is_never_granted_or_revoked_even_if_the_core_returned_it(string $roleName): void
    {
        RoleModel::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $this->authorization()->assignRole($this->user, $roleName);
        $this->app->instance(AssociationMembershipServiceInterface::class, new readonly class($roleName) implements AssociationMembershipServiceInterface
        {
            public function __construct(private string $roleName) {}

            public function roleName(): string
            {
                return $this->roleName;
            }

            public function isAssociationMember(object $user): bool
            {
                return false;
            }
        });
        $log = Log::spy();

        // A regular member without a membership would lose the role
        $this->member->update(['notes' => 'Revisada', 'status' => 'suspended']);

        $this->assertTrue($this->authorization()->hasRole($this->user, $roleName));
        $log->shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_starts_with($message, 'Memberships:'))->atLeast()->once();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function baseRoles(): array
    {
        return [
            'member' => ['member'],
            'editor' => ['editor'],
            'admin' => ['Admin'],
        ];
    }

    public function test_deleting_the_member_revokes_the_role(): void
    {
        $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $this->member->delete();

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_restoring_the_member_grants_the_role_again(): void
    {
        $this->membership('active');
        $this->member->delete();
        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));

        $this->member->restore();

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_force_deleting_the_member_revokes_the_role(): void
    {
        $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $this->member->forceDelete();

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_changing_the_linked_user_moves_the_role(): void
    {
        $this->membership('active');
        $other = UserModel::factory()->create();
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertFalse($this->authorization()->hasRole($other, 'socio'));

        $this->member->update(['user_id' => $other->id]);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($other, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($this->user, 'member'));
    }

    public function test_changing_the_linked_user_of_a_member_that_does_not_qualify_grants_nothing(): void
    {
        $other = UserModel::factory()->create();
        $this->authorization()->assignRole($this->user, 'socio');

        $this->member->update(['user_id' => $other->id]);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertFalse($this->authorization()->hasRole($other, 'socio'));
    }

    public function test_unlinking_the_user_revokes_the_role(): void
    {
        $this->membership('active');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $this->member->update(['user_id' => null]);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_the_role_is_kept_while_another_member_linked_to_the_user_is_active(): void
    {
        $this->membership('active');
        $other = $this->linkedMember($this->user);
        $membership = MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $other->id,
            'period_type' => 'calendar_year',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'active',
        ]);

        $membership->update(['status' => 'cancelled']);

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_a_suspended_second_member_does_not_take_the_role_of_an_active_one(): void
    {
        $this->membership('active');
        $suspended = $this->linkedMember($this->user);
        MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $suspended->id,
            'period_type' => 'calendar_year',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'active',
        ]);

        $suspended->update(['status' => 'suspended']);

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_a_deleted_member_does_not_keep_the_role(): void
    {
        $deleted = $this->linkedMember($this->user, 'honorary');
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $deleted->delete();

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_nothing_is_revoked_while_role_assignment_is_disabled(): void
    {
        $membership = $this->membership('active');
        config()->set('modules.settings.memberships.enable_role_assignment', false);

        $membership->update(['status' => 'cancelled']);
        $this->member->update(['status' => 'suspended']);

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_cancelling_from_a_stale_copy_revokes_the_role(): void
    {
        $membership = $this->membership('pending');
        $staleCopy = MembershipModel::query()->findOrFail($membership->id);

        $membership->update(['status' => 'active']);
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        // The stale copy still believes the membership is pending
        $staleCopy->update(['status' => 'cancelled']);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_deleting_a_stale_copy_revokes_the_role(): void
    {
        $membership = $this->membership('pending');
        $staleCopy = MembershipModel::query()->findOrFail($membership->id);

        $membership->update(['status' => 'active']);
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));

        $staleCopy->delete();

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_a_stale_copy_saving_other_fields_leaves_the_role_matching_the_database(): void
    {
        $membership = $this->membership('pending');
        $staleCopy = MembershipModel::query()->findOrFail($membership->id);

        $membership->update(['status' => 'active']);
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
        $membership->update(['status' => 'cancelled']);
        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));

        // The stale copy believes the membership is pending; Eloquent writes only the notes
        $staleCopy->update(['notes' => 'Revisada']);

        $this->assertSame('cancelled', MembershipModel::query()->findOrFail($membership->id)->status->value);
        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
    }

    public function test_moving_an_active_membership_to_another_member_moves_the_role(): void
    {
        $membership = $this->membership('active');
        $otherUser = UserModel::factory()->create();
        $otherMember = $this->linkedMember($otherUser);
        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertFalse($this->authorization()->hasRole($otherUser, 'socio'));

        $membership->update(['member_id' => $otherMember->id]);

        $this->assertFalse($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($otherUser, 'socio'));
    }

    public function test_moving_a_membership_keeps_the_role_of_a_member_with_another_active_one(): void
    {
        $this->membership('active', now()->startOfYear(), now()->endOfYear());
        $moved = $this->membership('active', now()->addYear()->startOfYear(), now()->addYear()->endOfYear());
        $otherUser = UserModel::factory()->create();
        $otherMember = $this->linkedMember($otherUser);

        $moved->update(['member_id' => $otherMember->id]);

        $this->assertTrue($this->authorization()->hasRole($this->user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($otherUser, 'socio'));
    }

    private function linkedMember(UserModel $user, string $type = 'regular'): MemberModel
    {
        $this->memberCount++;

        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2026-%04d', $this->memberCount),
            'first_name' => 'Luis',
            'last_name' => 'García',
            'member_type' => $type,
            'status' => 'active',
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);
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

    private function configureCoreRole(string $roleName): void
    {
        $this->app->make(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, $roleName);
    }

    private function authorization(): AuthorizationServiceInterface
    {
        return $this->app->make(AuthorizationServiceInterface::class);
    }
}
