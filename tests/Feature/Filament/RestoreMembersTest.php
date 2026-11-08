<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Memberships\Filament\RelationManagers\FeesRelationManager;
use Modules\Memberships\Filament\RelationManagers\MembershipsRelationManager;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\EditMember;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\ListMembers;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Deleted members can be listed and restored from the panel with the permission that
 * deletes them, and what hangs from them (memberships, fees, payments) is left alone.
 */
final class RestoreMembersTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string DELETE_PERMISSION = 'memberships:members.delete';

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([MemberResource::class, FeeStructureResource::class]);
    }

    public function test_deleted_members_are_hidden_until_the_deleted_filter_is_chosen(): void
    {
        $this->actingAsPanelAdmin();
        $active = $this->member('0001');
        $deleted = $this->member('0002');
        $deleted->delete();

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$deleted])
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$active, $deleted])
            ->filterTable('trashed', false)
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_restoring_a_member_from_the_list_brings_it_back(): void
    {
        $this->actingAsPanelAdmin();
        $member = $this->member('0001');
        $member->delete();

        Livewire::test(ListMembers::class)
            ->filterTable('trashed', false)
            ->callAction(TestAction::make('restore')->table($member))
            ->assertNotified();

        $this->assertFalse($this->reload($member)->trashed());
        Livewire::test(ListMembers::class)->assertCanSeeTableRecords([$member]);
    }

    public function test_restoring_a_member_leaves_its_memberships_fees_and_payments_as_they_were(): void
    {
        $this->actingAsPanelAdmin();
        $member = $this->member('0001');
        [$membership, $fee] = $this->membershipWithPaidFee($member);
        $member->delete();

        $this->assertSame(1, MembershipModel::query()->where('member_id', $member->id)->count());
        $this->assertNotNull($this->reloadFee($fee)->paid_at);

        Livewire::test(ListMembers::class)
            ->filterTable('trashed', false)
            ->callAction(TestAction::make('restore')->table($member));

        $this->assertSame(1, MembershipModel::query()->where('member_id', $member->id)->count());
        $this->assertNotNull($this->reloadFee($fee)->paid_at);
        $this->assertSame(1, MembershipFeeModel::query()->where('membership_id', $membership->id)->count());
    }

    public function test_a_member_can_be_restored_in_bulk(): void
    {
        $this->actingAsPanelAdmin();
        $first = $this->member('0001');
        $second = $this->member('0002');
        $first->delete();
        $second->delete();

        Livewire::test(ListMembers::class)
            ->filterTable('trashed', false)
            ->selectTableRecords([$first->getKey(), $second->getKey()])
            ->callAction(TestAction::make('restore')->table()->bulk())
            ->assertNotified();

        $this->assertFalse($this->reload($first)->trashed());
        $this->assertFalse($this->reload($second)->trashed());
    }

    public function test_row_actions_follow_the_state_of_the_record(): void
    {
        $this->actingAsPanelAdmin();
        $active = $this->member('0001');
        $deleted = $this->member('0002');
        $deleted->delete();

        $table = $this->tableOf(Livewire::test(ListMembers::class)->filterTable('trashed', true));

        // Filament caches a grouped action's visibility by the record's object id, so each
        // action is asked about the test's own record
        $this->assertTrue($this->rowAction($table, 'restore', $active)->isHidden());
        $this->assertFalse($this->rowAction($table, 'restore', $deleted)->isHidden());
        $this->assertFalse($this->rowAction($table, 'delete', $active)->isHidden());
        $this->assertTrue($this->rowAction($table, 'delete', $deleted)->isHidden());
    }

    public function test_restoring_needs_the_delete_permission(): void
    {
        $member = $this->member('0001');
        $member->delete();

        $this->actingAs($this->editorWith([self::DELETE_PERMISSION, 'memberships:members.view_any']));
        $table = $this->tableOf(Livewire::test(ListMembers::class)->filterTable('trashed', false));
        $this->assertFalse($this->rowAction($table, 'restore', $member)->isHidden());

        $this->actingAs($this->editorWith(['memberships:members.view_any']));
        $table = $this->tableOf(Livewire::test(ListMembers::class)->filterTable('trashed', false));
        $this->assertTrue($this->rowAction($table, 'restore', $member)->isHidden());
    }

    public function test_a_forged_restore_without_the_permission_changes_nothing(): void
    {
        $member = $this->member('0001');
        $member->delete();
        $this->actingAs($this->editorWith(['memberships:members.view_any']));

        Livewire::test(ListMembers::class)
            ->filterTable('trashed', false)
            ->mountAction(TestAction::make('restore')->table($member))
            ->callMountedAction();

        $this->assertTrue($this->reload($member)->trashed());
    }

    public function test_the_row_actions_still_sit_in_one_menu_with_restore(): void
    {
        $this->actingAsPanelAdmin();

        $actions = $this->tableOf(Livewire::test(ListMembers::class))->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
    }

    public function test_a_deleted_member_opens_read_only_with_its_memberships_and_payments(): void
    {
        $this->actingAsPanelAdmin();
        $member = $this->member('0001');
        [$membership, $fee] = $this->membershipWithPaidFee($member);
        $member->delete();

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertOk()
            ->assertActionVisible('restore')
            ->assertActionHidden('delete');

        $memberships = Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => EditMember::class]);
        $this->assertTrue($this->readOnly($memberships));
        $memberships->assertCanSeeTableRecords([$membership]);

        $fees = Livewire::test(FeesRelationManager::class, ['ownerRecord' => $member, 'pageClass' => EditMember::class]);
        $this->assertTrue($this->readOnly($fees));
        $fees->assertCanSeeTableRecords([$fee]);
    }

    public function test_saving_a_deleted_member_changes_nothing(): void
    {
        $this->actingAsPanelAdmin();
        $member = $this->member('0001');
        $member->delete();

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->fillForm(['first_name' => 'Changed'])
            ->call('save');

        $this->assertSame('Ana', MemberModel::withTrashed()->findOrFail($member->id)->first_name);
    }

    public function test_an_active_member_edit_page_is_not_read_only(): void
    {
        $this->actingAsPanelAdmin();
        $member = $this->member('0001');

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertActionVisible('delete')
            ->assertActionHidden('restore');

        $memberships = Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $member, 'pageClass' => EditMember::class]);
        $this->assertFalse($this->readOnly($memberships));
    }

    public function test_restoring_from_the_edit_page_brings_the_member_back(): void
    {
        $this->actingAsPanelAdmin();
        $member = $this->member('0001');
        $member->delete();

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->callAction('restore')
            ->assertNotified();

        $this->assertFalse($this->reload($member)->trashed());
    }

    public function test_a_deleted_member_row_offers_to_view_the_record_instead_of_editing_it(): void
    {
        $this->actingAsPanelAdmin();
        $active = $this->member('0001');
        $deleted = $this->member('0002');
        $deleted->delete();

        $table = $this->tableOf(Livewire::test(ListMembers::class)->filterTable('trashed', true));

        $this->assertSame('Ver ficha', $this->rowAction($table, 'edit', $deleted)->getLabel());
        $this->assertNotSame('Ver ficha', $this->rowAction($table, 'edit', $active)->getLabel());
    }

    /**
     * @param  Testable<ListMembers>  $component
     */
    private function tableOf(Testable $component): Table
    {
        return $component->instance()->getTable();
    }

    /**
     * @param  Testable<MembershipsRelationManager>|Testable<FeesRelationManager>  $component
     */
    private function readOnly(Testable $component): bool
    {
        return $component->instance()->isReadOnly();
    }

    private function rowAction(Table $table, string $name, MemberModel $record): Action
    {
        $action = $table->getAction($name);
        $this->assertInstanceOf(Action::class, $action);

        return $action->record($record);
    }

    private function reloadFee(MembershipFeeModel $fee): MembershipFeeModel
    {
        return MembershipFeeModel::query()->whereKey($fee->getKey())->firstOrFail();
    }

    private function reload(MemberModel $member): MemberModel
    {
        return MemberModel::withTrashed()->findOrFail($member->id);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function editorWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'members', 'action' => 'manage', 'module' => 'memberships'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * @return array{MembershipModel, MembershipFeeModel}
     */
    private function membershipWithPaidFee(MemberModel $member): array
    {
        $membership = MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);
        $fee = MembershipFeeModel::create([
            'membership_id' => $membership->id,
            'amount' => 30,
            'currency' => 'EUR',
            'due_date' => '2026-03-10',
            'paid_at' => '2026-03-01 10:00:00',
        ]);

        return [$membership, $fee];
    }

    private function member(string $number): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => $number,
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => "ana{$number}@example.test",
            'member_type' => 'regular',
            'status' => 'active',
            'joined_at' => '2025-01-01',
        ]);
    }
}
