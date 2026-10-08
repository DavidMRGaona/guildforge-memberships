<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Filament;

use App\Filament\Pages\ModuleSettingsPage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Memberships\Filament\Pages\MembershipSettings;
use Modules\Memberships\Filament\RelationManagers\MembershipsRelationManager;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages\CreateFeeStructure;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages\EditFeeStructure;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages\ListFeeStructures;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\CreateMember;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\EditMember;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\ListMembers;
use Modules\Memberships\Filament\Widgets\ExpiringMembershipsWidget;
use Modules\Memberships\Filament\Widgets\MembershipStatsWidget;
use Modules\Memberships\Filament\Widgets\OverdueFeesWidget;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class MembershipsPanelTest extends ModuleTestCase
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

    public function test_member_pages_render(): void
    {
        $member = $this->member();

        Livewire::test(ListMembers::class)->assertOk()->assertCanSeeTableRecords([$member]);
        Livewire::test(CreateMember::class)->assertOk();
        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertOk()
            ->assertFormSet(['first_name' => 'Ana']);
    }

    public function test_memberships_relation_manager_lists_the_member_memberships(): void
    {
        $member = $this->member();
        $membership = MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'active',
        ]);

        Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => EditMember::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$membership]);
    }

    public function test_fee_structure_pages_render(): void
    {
        $feeStructure = FeeStructureModel::create([
            'id' => (string) Str::uuid(),
            'member_type' => 'regular',
            'period_type' => 'calendar_year',
            'amount' => 30,
            'valid_from' => now()->startOfYear(),
        ]);

        Livewire::test(ListFeeStructures::class)->assertOk()->assertCanSeeTableRecords([$feeStructure]);
        Livewire::test(CreateFeeStructure::class)->assertOk();
        Livewire::test(EditFeeStructure::class, ['record' => $feeStructure->getRouteKey()])->assertOk();
    }

    public function test_settings_pages_render(): void
    {
        Livewire::test(MembershipSettings::class)->assertOk();
        Livewire::test(ModuleSettingsPage::class, ['module' => 'memberships'])->assertOk();
    }

    public function test_widgets_render(): void
    {
        foreach ([MembershipStatsWidget::class, ExpiringMembershipsWidget::class, OverdueFeesWidget::class] as $widget) {
            Livewire::test($widget)->assertOk();
        }
    }

    private function member(): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => '0001',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.test',
            'member_type' => 'regular',
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }
}
