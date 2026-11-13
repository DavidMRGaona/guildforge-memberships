<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Filament;

use Livewire\Livewire;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages\CreateFeeStructure;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages\EditFeeStructure;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Marking a fee structure as the default in the panel takes the mark from the previous default of
 * the same member type and period type, and says so.
 */
final class DefaultFeeStructurePanelTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([FeeStructureResource::class]);
        $this->actingAsPanelAdmin();
    }

    public function test_creating_a_default_takes_the_mark_from_the_previous_one_and_says_so(): void
    {
        $old = $this->feeStructure(isDefault: true, amount: 25);

        Livewire::test(CreateFeeStructure::class)
            ->fillForm([
                'member_type' => 'regular',
                'period_type' => 'calendar_year',
                'amount' => '30',
                'currency' => 'EUR',
                'valid_from' => '2026-01-01',
                'is_default' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Tarifa por defecto cambiada');

        $this->assertFalse($old->refresh()->is_default);
        $this->assertSame(1, FeeStructureModel::query()->where('is_default', true)->count());
    }

    public function test_editing_a_fee_structure_into_the_default_takes_the_mark_and_says_so(): void
    {
        $old = $this->feeStructure(isDefault: true, amount: 25);
        $other = $this->feeStructure(isDefault: false, amount: 30);

        Livewire::test(EditFeeStructure::class, ['record' => $other->getRouteKey()])
            ->fillForm(['is_default' => true])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Tarifa por defecto cambiada');

        $this->assertFalse($old->refresh()->is_default);
        $this->assertTrue($other->refresh()->is_default);
    }

    public function test_a_first_default_says_nothing_about_another(): void
    {
        $this->feeStructure(isDefault: true, memberType: 'student');

        Livewire::test(CreateFeeStructure::class)
            ->fillForm([
                'member_type' => 'regular',
                'period_type' => 'calendar_year',
                'amount' => '30',
                'currency' => 'EUR',
                'valid_from' => '2026-01-01',
                'is_default' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotNotified('Tarifa por defecto cambiada');
    }

    public function test_an_amount_with_a_trailing_newline_is_refused(): void
    {
        Livewire::test(CreateFeeStructure::class)
            ->fillForm([
                'member_type' => 'regular',
                'period_type' => 'calendar_year',
                'amount' => "30\n",
                'currency' => 'EUR',
                'valid_from' => '2026-01-01',
                'is_default' => false,
            ])
            ->call('create')
            ->assertHasFormErrors(['amount']);

        $this->assertSame(0, FeeStructureModel::query()->count());
    }

    public function test_the_default_checkbox_explains_that_there_is_only_one(): void
    {
        Livewire::test(CreateFeeStructure::class)
            ->assertSee('Solo puede haber una por tipo de socio y tipo de periodo');
    }

    private function feeStructure(bool $isDefault, int $amount = 30, string $memberType = 'regular'): FeeStructureModel
    {
        return FeeStructureModel::create([
            'member_type' => $memberType,
            'period_type' => 'calendar_year',
            'amount' => $amount,
            'valid_from' => '2025-01-01',
            'is_default' => $isDefault,
        ]);
    }
}
