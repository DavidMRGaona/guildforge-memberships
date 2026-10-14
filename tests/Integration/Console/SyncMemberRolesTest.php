<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Console;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Memberships\Console\Commands\SyncMemberRoles;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Modules\ModuleTestCase;

final class SyncMemberRolesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private int $memberCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        RoleModel::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        RoleModel::firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        config()->set('modules.settings.memberships.permanent_member_types', ['honorary']);

        // The module is enabled after the console kernel has loaded its commands
        Artisan::registerCommand($this->app->make(SyncMemberRoles::class));
    }

    public function test_it_grants_the_role_to_users_with_an_active_membership(): void
    {
        $user = $this->user();
        $this->membership($this->member($user));
        $this->assertFalse($this->authorization()->hasRole($user, 'socio'));
        $this->enable();

        [$exitCode, $output] = $this->sync();
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString($this->summary(1, 1, 0), $output);

        $this->assertTrue($this->authorization()->hasRole($user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($user, 'member'));
    }

    public function test_it_grants_the_role_to_active_honorary_members_without_a_membership(): void
    {
        $honorary = $this->user();
        $this->member($honorary, 'honorary');
        $suspendedHonorary = $this->user();
        $this->member($suspendedHonorary, 'honorary', 'suspended');
        $this->enable();

        [$exitCode, $output] = $this->sync();
        $this->assertSame(0, $exitCode, $output);

        $this->assertTrue($this->authorization()->hasRole($honorary, 'socio'));
        $this->assertFalse($this->authorization()->hasRole($suspendedHonorary, 'socio'));
    }

    public function test_it_revokes_the_role_from_linked_users_that_no_longer_qualify(): void
    {
        $expired = $this->user('socio');
        $this->membership($this->member($expired), 'expired');
        $suspended = $this->user('socio');
        $this->membership($this->member($suspended, 'regular', 'suspended'));
        $unlinked = $this->user('socio');
        $this->enable();

        [$exitCode, $output] = $this->sync();
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString($this->summary(2, 0, 2), $output);

        $this->assertFalse($this->authorization()->hasRole($expired, 'socio'));
        $this->assertFalse($this->authorization()->hasRole($suspended, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($expired, 'member'));
        // Users no member is linked to keep the roles granted by hand
        $this->assertTrue($this->authorization()->hasRole($unlinked, 'socio'));
    }

    public function test_dropping_honorary_from_the_permanent_types_revokes_the_role_on_sync(): void
    {
        $this->enable();
        $honorary = $this->user();
        $this->member($honorary, 'honorary');
        $withMembership = $this->user();
        $this->membership($this->member($withMembership, 'honorary'));
        $this->assertTrue($this->authorization()->hasRole($honorary, 'socio'));

        config()->set('modules.settings.memberships.permanent_member_types', []);
        [$exitCode, $output] = $this->sync();
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString($this->summary(2, 0, 1), $output);

        $this->assertFalse($this->authorization()->hasRole($honorary, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($withMembership, 'socio'));
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $granted = $this->user();
        $this->membership($this->member($granted));
        $revoked = $this->user('socio');
        $this->member($revoked);
        $this->enable();

        [$exitCode, $output] = $this->sync();
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString($this->summary(2, 1, 1), $output);
        [$exitCode, $output] = $this->sync();
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString($this->summary(2, 0, 0), $output);

        $this->assertTrue($this->authorization()->hasRole($granted, 'socio'));
        $this->assertFalse($this->authorization()->hasRole($revoked, 'socio'));
    }

    public function test_a_dry_run_reports_the_changes_without_writing_them(): void
    {
        $toGrant = $this->user();
        $this->membership($this->member($toGrant));
        $toRevoke = $this->user('socio');
        $this->member($toRevoke);
        $this->enable();

        [$exitCode, $output] = $this->sync(['--dry-run' => true]);
        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString($this->summary(2, 1, 1), $output);
        $this->assertStringContainsString(__('memberships::memberships.commands.sync_roles.dry_run'), $output);

        $this->assertFalse($this->authorization()->hasRole($toGrant, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($toRevoke, 'socio'));
    }

    public function test_it_changes_nothing_while_role_assignment_is_disabled(): void
    {
        config()->set('modules.settings.memberships.enable_role_assignment', false);
        $user = $this->user('socio');
        $this->member($user);

        [$exitCode, $output] = $this->sync();
        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString(__('memberships::memberships.commands.sync_roles.disabled'), $output);

        $this->assertTrue($this->authorization()->hasRole($user, 'socio'));
    }

    public function test_it_changes_nothing_while_the_core_role_cannot_be_used(): void
    {
        $this->app->make(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'member');
        $this->enable();
        $user = $this->user('socio');
        $this->member($user);

        [$exitCode, $output] = $this->sync();
        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString(__('memberships::memberships.commands.sync_roles.role_unavailable'), $output);

        $this->assertTrue($this->authorization()->hasRole($user, 'socio'));
        $this->assertTrue($this->authorization()->hasRole($user, 'member'));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{int, string}
     */
    private function sync(array $options = []): array
    {
        $exitCode = Artisan::call('memberships:sync-roles', $options);

        return [$exitCode, Artisan::output()];
    }

    private function enable(): void
    {
        config()->set('modules.settings.memberships.enable_role_assignment', true);
    }

    private function summary(int $checked, int $added, int $removed): string
    {
        return __('memberships::memberships.commands.sync_roles.summary', [
            'checked' => $checked,
            'added' => $added,
            'removed' => $removed,
        ]);
    }

    private function user(?string $extraRole = null): UserModel
    {
        $user = UserModel::factory()->create();
        $this->authorization()->assignRole($user, 'member');

        if ($extraRole !== null) {
            $this->authorization()->assignRole($user, $extraRole);
        }

        return $user;
    }

    private function member(UserModel $user, string $type = 'regular', string $status = 'active'): MemberModel
    {
        $this->memberCount++;

        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2026-%04d', $this->memberCount),
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'member_type' => $type,
            'status' => $status,
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);
    }

    private function membership(MemberModel $member, string $status = 'active'): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => $status,
        ]);
    }

    private function authorization(): AuthorizationServiceInterface
    {
        return $this->app->make(AuthorizationServiceInterface::class);
    }
}
