<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Filament\RelationManagers\FeesRelationManager;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\EditMember;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * The «Pagos» tab of a member: the fees of all their memberships, created pending or already
 * paid, with the amount proposed from the default fee structure, and payments recorded later.
 */
final class FeesRelationManagerTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;
    use UsesUtcStorage;

    private const string VIEW = 'memberships:fees.view_any';

    private const string MANAGE = 'memberships:fees.manage';

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private MemberModel $member;

    private MembershipModel $membership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->registerOnAdminPanel([MemberResource::class]);
        $this->actingAsPanelAdmin();
        $this->member = $this->member('student');
        $this->membership = $this->membership($this->member);
    }

    public function test_the_tab_lists_the_fees_of_every_membership_of_the_member_and_no_one_else(): void
    {
        $previous = $this->membership($this->member, '2025-01-01', '2025-12-31');
        $own = $this->fee($this->membership);
        $older = $this->fee($previous);
        $someoneElse = $this->fee($this->membership($this->member('regular')));

        $this->tab()
            ->assertOk()
            ->assertCanSeeTableRecords([$own, $older])
            ->assertCanNotSeeTableRecords([$someoneElse]);
    }

    public function test_the_tab_tells_paid_pending_and_overdue_fees_apart(): void
    {
        $this->travelTo('2026-03-10 12:00:00');
        $this->fee($this->membership, dueDate: '2026-02-01', paidAt: '2026-02-01 10:00:00');
        $this->fee($this->membership, dueDate: '2026-04-01');
        $this->fee($this->membership, dueDate: '2026-03-01');

        $this->tab()->assertSee(['Pagado', 'Pendiente', 'Vencido']);
    }

    public function test_a_new_fee_proposes_the_amount_of_the_default_fee_structure(): void
    {
        $this->defaultFeeStructure('student', 'calendar_year', 25);
        $this->defaultFeeStructure('regular', 'calendar_year', 60);

        $this->tab()
            ->mountAction(TestAction::make('create')->table())
            ->fillForm(['membership_id' => $this->membership->id])
            ->assertSchemaStateSet(['amount' => '25.00', 'currency' => 'EUR']);
    }

    public function test_creating_a_pending_fee(): void
    {
        $this->tab()
            ->callAction(TestAction::make('create')->table(), data: [
                'membership_id' => $this->membership->id,
                'amount' => '30',
                'currency' => 'EUR',
                'due_date' => '2026-02-01',
                'is_paid' => false,
                'notes' => 'Primer plazo',
            ])
            ->assertHasNoFormErrors();

        $fee = MembershipFeeModel::query()->sole();
        $this->assertSame($this->membership->id, $fee->membership_id);
        $this->assertSame('30.00', $fee->amount);
        $this->assertSame('2026-02-01', $fee->due_date->toDateString());
        $this->assertNull($fee->paid_at);
        $this->assertSame('Primer plazo', $fee->notes);
    }

    public function test_creating_a_fee_already_paid_records_the_payment(): void
    {
        $this->tab()
            ->callAction(TestAction::make('create')->table(), data: [
                'membership_id' => $this->membership->id,
                'amount' => '30',
                'currency' => 'EUR',
                'due_date' => '2026-02-01',
                'is_paid' => true,
                'paid_on' => '2026-01-28',
                'payment_method' => PaymentMethod::BankTransfer->value,
                'transaction_reference' => 'TR-123',
            ])
            ->assertHasNoFormErrors();

        $fee = MembershipFeeModel::query()->sole();
        $this->assertSame(PaymentMethod::BankTransfer, $fee->payment_method);
        $this->assertSame('TR-123', $fee->transaction_reference);
        // A calendar day of the display zone (Madrid), stored as its midnight in UTC
        $this->assertSame('2026-01-27 23:00:00', $fee->getRawOriginal('paid_at'));
    }

    public function test_a_paid_fee_needs_its_payment_date_and_method(): void
    {
        $this->tab()
            ->callAction(TestAction::make('create')->table(), data: [
                'membership_id' => $this->membership->id,
                'amount' => '30',
                'currency' => 'EUR',
                'due_date' => '2026-02-01',
                'is_paid' => true,
                'paid_on' => null,
            ])
            ->assertHasFormErrors(['paid_on' => 'required', 'payment_method' => 'required']);

        $this->assertSame(0, MembershipFeeModel::query()->count());
    }

    public function test_a_fee_only_takes_a_membership_of_this_member(): void
    {
        $someoneElses = $this->membership($this->member('regular'));

        $this->tab()
            ->callAction(TestAction::make('create')->table(), data: [
                'membership_id' => $someoneElses->id,
                'amount' => '30',
                'currency' => 'EUR',
                'due_date' => '2026-02-01',
                'is_paid' => false,
            ])
            ->assertHasFormErrors(['membership_id']);

        $this->assertSame(0, MembershipFeeModel::query()->count());
    }

    public function test_recording_the_payment_of_a_pending_fee(): void
    {
        $fee = $this->fee($this->membership);

        $this->tab()
            ->callAction(TestAction::make('recordPayment')->table($fee), data: [
                'paid_on' => '2026-02-03',
                'payment_method' => PaymentMethod::Cash->value,
            ])
            ->assertHasNoFormErrors();

        $fee->refresh();
        $this->assertSame(PaymentMethod::Cash, $fee->payment_method);
        $this->assertSame('2026-02-02 23:00:00', $fee->getRawOriginal('paid_at'));
    }

    public function test_a_paid_fee_offers_no_payment_to_record(): void
    {
        $fee = $this->fee($this->membership, paidAt: '2026-02-01 10:00:00');

        $this->tab()->assertActionHidden(TestAction::make('recordPayment')->table($fee));
    }

    public function test_deleting_a_fee_asks_to_confirm_that_it_cannot_be_undone(): void
    {
        $fee = $this->fee($this->membership);

        $this->tab()
            ->callAction(TestAction::make('delete')->table($fee), data: ['confirmation' => false])
            ->assertHasFormErrors(['confirmation' => 'accepted']);
        $this->assertTrue(MembershipFeeModel::query()->whereKey($fee->id)->exists());

        $this->tab()
            ->callAction(TestAction::make('delete')->table($fee), data: ['confirmation' => true])
            ->assertHasNoFormErrors();
        $this->assertFalse(MembershipFeeModel::query()->whereKey($fee->id)->exists());
    }

    public function test_the_tab_is_hidden_without_permission_to_see_payments(): void
    {
        $this->actingAs($this->editorWithPermissions(['memberships:members.view_any', 'memberships:members.update']));

        $this->assertFalse(FeesRelationManager::canViewForRecord($this->member, EditMember::class));
    }

    public function test_seeing_payments_is_not_managing_them(): void
    {
        $fee = $this->fee($this->membership);
        $this->actingAs($this->editorWithPermissions(['memberships:members.update', self::VIEW]));

        $this->assertTrue(FeesRelationManager::canViewForRecord($this->member, EditMember::class));
        $this->tab()
            ->assertCanSeeTableRecords([$fee])
            ->assertActionHidden(TestAction::make('create')->table())
            ->assertActionHidden(TestAction::make('recordPayment')->table($fee))
            ->assertActionHidden(TestAction::make('edit')->table($fee))
            ->assertActionHidden(TestAction::make('delete')->table($fee));
    }

    public function test_managing_payments_offers_every_action(): void
    {
        $fee = $this->fee($this->membership);
        $this->actingAs($this->editorWithPermissions(['memberships:members.update', self::VIEW, self::MANAGE]));

        $this->tab()
            ->assertActionVisible(TestAction::make('create')->table())
            ->assertActionVisible(TestAction::make('recordPayment')->table($fee))
            ->assertActionVisible(TestAction::make('edit')->table($fee))
            ->assertActionVisible(TestAction::make('delete')->table($fee));
    }

    /**
     * @return Testable<FeesRelationManager>
     */
    private function tab(): Testable
    {
        return Livewire::test(FeesRelationManager::class, ['ownerRecord' => $this->member, 'pageClass' => EditMember::class]);
    }

    private function member(string $memberType): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => 'S-'.Str::random(6),
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => Str::random(8).'@example.com',
            'member_type' => $memberType,
            'status' => 'active',
            'joined_at' => '2025-01-01',
        ]);
    }

    private function membership(MemberModel $member, string $start = '2026-01-01', string $end = '2026-12-31'): MembershipModel
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

    private function fee(MembershipModel $membership, string $dueDate = '2026-02-01', ?string $paidAt = null): MembershipFeeModel
    {
        return MembershipFeeModel::create([
            'membership_id' => $membership->id,
            'amount' => 30,
            'currency' => 'EUR',
            'due_date' => $dueDate,
            'paid_at' => $paidAt,
            'payment_method' => $paidAt === null ? null : PaymentMethod::Cash,
        ]);
    }

    private function defaultFeeStructure(string $memberType, string $periodType, int $amount): void
    {
        FeeStructureModel::create([
            'member_type' => $memberType,
            'period_type' => $periodType,
            'amount' => $amount,
            'valid_from' => '2026-01-01',
            'is_default' => true,
        ]);
    }
}
