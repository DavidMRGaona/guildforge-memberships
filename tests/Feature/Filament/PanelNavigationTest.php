<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Filament;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Support\PanelNavigationGroup;
use Modules\Memberships\Filament\Pages\MembershipSettings;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\MemberResource;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel menu is grouped by task (core#409): members sit in «Personas» with the fee
 * structures under them, and the settings join the «Configuración» cluster.
 */
final class PanelNavigationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel(
            [MemberResource::class, FeeStructureResource::class],
            [MembershipSettings::class],
        );
        $this->actingAsPanelAdmin();
    }

    public function test_members_sit_in_the_people_group_in_their_own_slot(): void
    {
        $this->assertSame(PanelNavigationGroup::People->getLabel(), MemberResource::getNavigationGroup());
        $this->assertSame(30, MemberResource::getNavigationSort());
        $this->assertSame('Socios', MemberResource::getNavigationLabel());
        $this->assertNull(MemberResource::getNavigationParentItem());
    }

    public function test_fee_structures_are_a_child_of_members_in_the_same_group(): void
    {
        $this->assertSame(PanelNavigationGroup::People->getLabel(), FeeStructureResource::getNavigationGroup());
        $this->assertSame(MemberResource::getNavigationLabel(), FeeStructureResource::getNavigationParentItem());
        $this->assertSame('Tarifas', FeeStructureResource::getNavigationLabel());
    }

    public function test_settings_join_the_settings_cluster(): void
    {
        $this->assertSame(SettingsCluster::class, MembershipSettings::getCluster());
        $this->assertSame(170, MembershipSettings::getNavigationSort());
        $this->assertSame('Socios', MembershipSettings::getNavigationLabel());
        $this->assertNull(MembershipSettings::getNavigationGroup());
        $this->assertSame('Sistema', SettingsCluster::getNavigationGroup());
        $this->assertStringStartsWith('/admin/configuracion/', (string) parse_url(MembershipSettings::getUrl(), PHP_URL_PATH));
    }

    public function test_the_panel_menu_shows_members_under_the_people_group(): void
    {
        $this->get(MemberResource::getUrl())
            ->assertOk()
            ->assertSeeInOrder([PanelNavigationGroup::People->getLabel(), 'Socios']);
    }
}
