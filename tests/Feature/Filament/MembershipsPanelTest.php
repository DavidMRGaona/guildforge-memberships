<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Filament;

use App\Filament\Pages\ModuleSettingsPage;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\CreateAction;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Memberships\Domain\Enums\MemberType;
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
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;
use Modules\Memberships\MembershipsServiceProvider;
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

    public function test_the_settings_form_offers_the_permanent_member_types_and_no_role_name(): void
    {
        Livewire::test(ModuleSettingsPage::class, ['module' => 'memberships'])
            ->assertOk()
            ->assertFormFieldExists('permanent_member_types')
            ->assertFormFieldDoesNotExist('member_role_name')
            ->assertSee(__('memberships::memberships.enums.member_type.honorary'))
            ->assertSee(__('memberships::memberships.settings.fields.permanent_member_types_help'));
    }

    public function test_the_permanent_member_types_saved_in_the_panel_are_used(): void
    {
        Livewire::test(ModuleSettingsPage::class, ['module' => 'memberships'])
            ->fillForm(['permanent_member_types' => ['founder', 'honorary']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            [MemberType::Founder, MemberType::Honorary],
            $this->app->make(MembershipSettingsReader::class)->permanentMemberTypes(),
        );
    }

    public function test_widgets_render(): void
    {
        foreach ([MembershipStatsWidget::class, ExpiringMembershipsWidget::class, OverdueFeesWidget::class] as $widget) {
            Livewire::test($widget)->assertOk();
        }
    }

    public function test_the_settings_form_has_no_unused_fields(): void
    {
        Livewire::test(ModuleSettingsPage::class, ['module' => 'memberships'])
            ->assertOk()
            ->assertFormFieldExists('expiration_warning_days')
            ->assertFormFieldDoesNotExist('grace_period_days')
            ->assertFormFieldDoesNotExist('enable_proration')
            ->assertFormFieldDoesNotExist('default_currency');
    }

    public function test_an_empty_list_of_permanent_member_types_saved_in_the_panel_is_kept(): void
    {
        Livewire::test(ModuleSettingsPage::class, ['module' => 'memberships'])
            ->fillForm(['permanent_member_types' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        // The shipped default (honorary) must not come back over the saved empty list
        $this->assertSame([], $this->app->make(MembershipSettingsReader::class)->permanentMemberTypes());
        Livewire::test(ModuleSettingsPage::class, ['module' => 'memberships'])
            ->assertFormSet(['permanent_member_types' => []]);
    }

    public function test_the_expiring_memberships_widget_leaves_permanent_members_out(): void
    {
        $regular = $this->membershipFor($this->member(), now()->addDays(10));
        $honorary = $this->membershipFor($this->member('honorary', '0002'), now()->addDays(10));

        Livewire::test(ExpiringMembershipsWidget::class)
            ->assertCanSeeTableRecords([$regular])
            ->assertCanNotSeeTableRecords([$honorary]);
    }

    public function test_the_overdue_fees_widget_leaves_permanent_members_out(): void
    {
        $regular = $this->overdueFee($this->membershipFor($this->member(), now()->endOfYear()));
        $honorary = $this->overdueFee($this->membershipFor($this->member('honorary', '0002'), now()->endOfYear()));

        Livewire::test(OverdueFeesWidget::class)
            ->assertCanSeeTableRecords([$regular])
            ->assertCanNotSeeTableRecords([$honorary]);
    }

    public function test_a_type_left_out_of_the_permanent_types_shows_in_the_widgets_again(): void
    {
        config()->set('modules.settings.memberships.permanent_member_types', []);
        $membership = $this->membershipFor($this->member('honorary', '0002'), now()->addDays(10));
        $fee = $this->overdueFee($membership);

        Livewire::test(ExpiringMembershipsWidget::class)->assertCanSeeTableRecords([$membership]);
        Livewire::test(OverdueFeesWidget::class)->assertCanSeeTableRecords([$fee]);
    }

    public function test_new_memberships_default_to_the_period_type_saved_in_the_panel(): void
    {
        config()->set('modules.settings.memberships.default_period_type', 'rolling');

        Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $this->member(), 'pageClass' => EditMember::class])
            ->mountTableAction(CreateAction::class)
            ->assertTableActionDataSet(['period_type' => 'rolling']);
    }

    public function test_dashboard_widgets_are_registered(): void
    {
        $widgets = (new MembershipsServiceProvider($this->app))->registerFilamentWidgets();

        $this->assertSame(
            [MembershipStatsWidget::class, ExpiringMembershipsWidget::class, OverdueFeesWidget::class],
            $widgets,
        );
    }

    public function test_widgets_are_shown_to_users_who_can_list_members(): void
    {
        foreach ([MembershipStatsWidget::class, ExpiringMembershipsWidget::class, OverdueFeesWidget::class] as $widget) {
            $this->assertTrue($widget::canView(), $widget);
        }
    }

    public function test_widgets_are_hidden_without_permission_to_list_members(): void
    {
        $this->actingAs(UserModel::factory()->create());

        foreach ([MembershipStatsWidget::class, ExpiringMembershipsWidget::class, OverdueFeesWidget::class] as $widget) {
            $this->assertFalse($widget::canView(), $widget);
        }
    }

    public function test_the_expiry_command_is_scheduled_daily(): void
    {
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'memberships:process-expired'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events[0]->expression);
    }

    private function member(string $type = 'regular', string $number = '0001'): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => $number,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.test',
            'member_type' => $type,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    private function membershipFor(MemberModel $member, \DateTimeInterface $end): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => now()->startOfYear(),
            'end_date' => $end,
            'status' => 'active',
        ]);
    }

    private function overdueFee(MembershipModel $membership): MembershipFeeModel
    {
        return MembershipFeeModel::create([
            'id' => (string) Str::uuid(),
            'membership_id' => $membership->id,
            'amount' => 30,
            'currency' => 'EUR',
            'due_date' => now()->subDays(5),
        ]);
    }
}
