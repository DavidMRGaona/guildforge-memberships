<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Filament;

use App\Filament\Actions\RowDetailsAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Memberships\Filament\RelationManagers\FeesRelationManager;
use Modules\Memberships\Filament\RelationManagers\MembershipsRelationManager;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages\ListFeeStructures;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\EditMember;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\ListMembers;
use Modules\Memberships\Filament\Widgets\ExpiringMembershipsWidget;
use Modules\Memberships\Filament\Widgets\OverdueFeesWidget;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Spanish capitalization in the texts Filament builds from the model labels («Borrar cuota»,
 * not «Borrar Cuota»), and list tables whose row actions fit: they sit in a menu.
 */
final class PanelLabelsAndTablesTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([MemberResource::class, FeeStructureResource::class]);
        $this->actingAsPanelAdmin();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function singularLabels(): array
    {
        return [
            'member' => ['member'],
            'membership' => ['membership'],
            'fee' => ['fee'],
            'fee_structure' => ['fee_structure'],
        ];
    }

    #[DataProvider('singularLabels')]
    public function test_singular_model_labels_start_in_lowercase(string $model): void
    {
        $label = __("memberships::memberships.model.{$model}.singular");

        $this->assertSame(mb_lcfirst($label), $label);
    }

    public function test_deleting_a_membership_asks_in_spanish_capitalization(): void
    {
        $member = $this->member();
        $membership = MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        $component = Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => EditMember::class]);
        $component->mountAction(TestAction::make('delete')->table($membership))
            ->assertOk();

        $this->assertSame('Borrar cuota', $this->deleteHeading($component));
    }

    public function test_deleting_a_member_asks_in_spanish_capitalization(): void
    {
        $member = $this->member();

        $component = Livewire::test(ListMembers::class);
        $component->mountAction(TestAction::make('delete')->table($member))
            ->assertOk();

        $this->assertSame('Borrar socio', $this->deleteHeading($component));
    }

    public function test_deleting_a_fee_structure_asks_in_spanish_capitalization(): void
    {
        $feeStructure = FeeStructureModel::create([
            'member_type' => 'regular',
            'period_type' => 'calendar_year',
            'amount' => 30,
            'valid_from' => '2026-01-01',
        ]);

        $component = Livewire::test(ListFeeStructures::class);
        $component->mountAction(TestAction::make('delete')->table($feeStructure))
            ->assertOk();

        $this->assertSame('Borrar tarifa', $this->deleteHeading($component));
    }

    /**
     * @return array<string, array{class-string<HasTable>}>
     */
    public static function listPages(): array
    {
        return [
            'members' => [ListMembers::class],
            'fee structures' => [ListFeeStructures::class],
        ];
    }

    /**
     * The tables overflowed at 1280 px and hid «Borrar» (and half of «Editar»): their row
     * actions go in one menu, which takes the room of a single icon.
     *
     * @param  class-string<HasTable>  $page
     */
    #[DataProvider('listPages')]
    public function test_list_row_actions_sit_in_a_menu(string $page): void
    {
        /** @var HasTable $component */
        $component = Livewire::test($page)->instance();
        $actions = $component->getTable()->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
    }

    public function test_the_memberships_tab_row_actions_sit_in_a_menu(): void
    {
        $tab = Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $this->member(), 'pageClass' => EditMember::class])->instance();
        $this->assertInstanceOf(MembershipsRelationManager::class, $tab);
        $actions = $tab->getTable()->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
    }

    /**
     * «Ver detalles» (core#399): what the tables hide on narrow screens stays reachable.
     */
    #[DataProvider('listPages')]
    public function test_list_menus_start_with_row_details(string $page): void
    {
        /** @var HasTable $component */
        $component = Livewire::test($page)->instance();
        $group = $component->getTable()->getRecordActions()[0];

        $this->assertInstanceOf(ActionGroup::class, $group);
        $this->assertSame(RowDetailsAction::NAME, array_key_first($group->getFlatActions()));
        $this->assertNotNull($component->getTable()->getAction(RowDetailsAction::NAME));
    }

    public function test_relation_manager_menus_start_with_row_details(): void
    {
        $member = $this->member();

        foreach ([MembershipsRelationManager::class, FeesRelationManager::class] as $manager) {
            $tab = Livewire::test($manager, ['ownerRecord' => $member, 'pageClass' => EditMember::class])->instance();
            $this->assertInstanceOf($manager, $tab);
            $actions = $tab->getTable()->getRecordActions();
            $group = end($actions);

            $this->assertInstanceOf(ActionGroup::class, $group);
            $this->assertSame(RowDetailsAction::NAME, array_key_first($group->getFlatActions()), $manager);
        }
    }

    public function test_the_overdue_widget_has_the_row_details_icon_only(): void
    {
        $widget = Livewire::test(OverdueFeesWidget::class)->instance();
        $this->assertInstanceOf(OverdueFeesWidget::class, $widget);
        $actions = $widget->getTable()->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(Action::class, $actions[0]);
        $this->assertSame(RowDetailsAction::NAME, $actions[0]->getName());
        $this->assertTrue($actions[0]->isIconButton());
    }

    /**
     * The day counts of the dashboard widgets were built with a Spanish suffix in the code.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function dayCounts(): array
    {
        return [
            'spanish, several' => ['es', '2026-03-15', '5 días'],
            'spanish, one' => ['es', '2026-03-11', '1 día'],
            'english, several' => ['en', '2026-03-15', '5 days'],
            'english, one' => ['en', '2026-03-11', '1 day'],
        ];
    }

    #[DataProvider('dayCounts')]
    public function test_the_expiring_widget_counts_days_in_the_viewer_language(string $locale, string $end, string $expected): void
    {
        $this->travelTo('2026-03-10 12:00:00');
        app()->setLocale($locale);
        $membership = $this->membership($this->member(), '2026-01-01', $end);

        Livewire::test(ExpiringMembershipsWidget::class)
            ->assertTableColumnFormattedStateSet('days_remaining', $expected, $membership);
    }

    #[DataProvider('dayCounts')]
    public function test_the_overdue_widget_counts_days_in_the_viewer_language(string $locale, string $today, string $expected): void
    {
        $this->travelTo($today.' 12:00:00');
        app()->setLocale($locale);
        $fee = MembershipFeeModel::create([
            'membership_id' => $this->membership($this->member(), '2026-01-01', '2026-12-31')->id,
            'amount' => 30,
            'currency' => 'EUR',
            'due_date' => '2026-03-10',
        ]);

        Livewire::test(OverdueFeesWidget::class)
            ->assertTableColumnFormattedStateSet('days_overdue', $expected, $fee);
    }

    private function membership(MemberModel $member, string $start, string $end): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'active',
        ]);
    }

    /**
     * @param  Testable<ListMembers>|Testable<ListFeeStructures>|Testable<MembershipsRelationManager>  $component
     */
    private function deleteHeading(Testable $component): string
    {
        $action = $component->instance()->getMountedAction();
        $this->assertInstanceOf(Action::class, $action);
        $heading = $action->getModalHeading();
        $this->assertIsString($heading);

        return $heading;
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
            'joined_at' => '2025-01-01',
        ]);
    }
}
