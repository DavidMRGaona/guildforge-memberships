<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Filament;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\Memberships\Filament\Pages\MembershipSettings;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;
use Modules\Memberships\MembershipsServiceProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The settings page opens with the memberships:settings.manage permission (admins hold every
 * permission), so the settings can be delegated to a role other than admin.
 */
final class MembershipSettingsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string PERMISSION = 'memberships:settings.manage';

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [MembershipSettings::class]);
    }

    public function test_the_provider_registers_the_page(): void
    {
        $this->assertContains(MembershipSettings::class, (new MembershipsServiceProvider(app()))->registerFilamentPages());
    }

    public function test_the_permission_is_registered_and_proposed_to_no_role(): void
    {
        $this->assertTrue(app(PermissionRegistryInterface::class)->has(self::PERMISSION));

        $settings = collect((new MembershipsServiceProvider(app()))->registerPermissions())
            ->firstWhere('name', 'settings.manage');
        $this->assertSame([], $settings?->roles);
    }

    public function test_an_admin_opens_the_settings_page(): void
    {
        $this->actingAsPanelAdmin();

        $this->assertTrue(MembershipSettings::canAccess());
        Livewire::test(MembershipSettings::class)->assertOk();
    }

    public function test_an_editor_granted_the_permission_opens_the_settings_page(): void
    {
        $this->actingAs($this->editorWith([self::PERMISSION]));

        $this->assertTrue(MembershipSettings::canAccess());
        Livewire::test(MembershipSettings::class)->assertOk();
    }

    public function test_an_editor_without_the_permission_cannot_open_the_settings_page(): void
    {
        $this->actingAs($this->editorWith([]));

        $this->assertFalse(MembershipSettings::canAccess());
        Livewire::test(MembershipSettings::class)->assertForbidden();
    }

    public function test_saving_is_refused_once_the_permission_is_gone(): void
    {
        $this->actingAs($this->editorWith([self::PERMISSION]));
        $page = Livewire::test(MembershipSettings::class)->fillForm(['expiration_warning_days' => 14]);

        $this->actingAs($this->editorWith([]));
        $page->call('save')->assertForbidden();

        $this->assertSame(30, app(MembershipSettingsReader::class)->expirationWarningDays());
    }

    public function test_the_form_shows_the_saved_values_over_the_release_defaults(): void
    {
        // Saved by an older release, before the email switches existed
        app(SettingsServiceInterface::class)->set(
            'module_settings:memberships',
            json_encode(['expiration_warning_days' => 14], JSON_THROW_ON_ERROR),
        );
        $this->actingAsPanelAdmin();

        Livewire::test(MembershipSettings::class)->assertFormSet([
            'expiration_warning_days' => 14,
            'academic_start_month' => 9,
            'permanent_member_types' => ['honorary'],
            'send_expired_email' => false,
            'send_overdue_email' => false,
        ]);
    }

    public function test_saving_stores_the_settings_the_module_reads(): void
    {
        $this->actingAs($this->editorWith([self::PERMISSION]));

        Livewire::test(MembershipSettings::class)
            ->fillForm(['expiration_warning_days' => 14, 'send_overdue_email' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $reader = app(MembershipSettingsReader::class);
        $this->assertSame(14, $reader->expirationWarningDays());
        $this->assertTrue($reader->overdueEmailEnabled());
    }

    public function test_the_warning_days_are_validated_on_the_server(): void
    {
        $this->actingAsPanelAdmin();

        foreach ([366, -1, null] as $invalid) {
            Livewire::test(MembershipSettings::class)
                ->fillForm(['expiration_warning_days' => $invalid])
                ->call('save')
                ->assertHasFormErrors(['expiration_warning_days']);
        }
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function editorWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'settings', 'action' => 'manage', 'module' => 'memberships'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
