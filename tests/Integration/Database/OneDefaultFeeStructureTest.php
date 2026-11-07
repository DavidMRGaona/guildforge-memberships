<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Database;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Only one fee structure is the default of each member type and period type: marking another
 * one moves the mark, and the database refuses a second default written around the model.
 */
final class OneDefaultFeeStructureTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    public function test_marking_a_fee_structure_as_default_takes_the_mark_from_the_previous_one(): void
    {
        $old = $this->feeStructure(isDefault: true, validFrom: '2025-01-01');

        $new = $this->feeStructure(isDefault: true, validFrom: '2026-01-01');

        $this->assertFalse($old->refresh()->is_default);
        $this->assertTrue($new->refresh()->is_default);
    }

    public function test_the_defaults_of_other_member_types_and_periods_keep_their_mark(): void
    {
        $student = $this->feeStructure(isDefault: true, memberType: 'student');
        $academic = $this->feeStructure(isDefault: true, periodType: 'academic_year');

        $this->feeStructure(isDefault: true);

        $this->assertTrue($student->refresh()->is_default);
        $this->assertTrue($academic->refresh()->is_default);
    }

    public function test_editing_a_fee_structure_into_the_default_moves_the_mark(): void
    {
        $old = $this->feeStructure(isDefault: true);
        $other = $this->feeStructure(isDefault: false);

        $other->update(['is_default' => true]);

        $this->assertFalse($old->refresh()->is_default);
        $this->assertTrue($other->refresh()->is_default);
    }

    public function test_moving_the_default_to_another_member_type_takes_the_mark_there(): void
    {
        $studentDefault = $this->feeStructure(isDefault: true, memberType: 'student');
        $regularDefault = $this->feeStructure(isDefault: true);

        $regularDefault->update(['member_type' => 'student']);

        $this->assertFalse($studentDefault->refresh()->is_default);
        $this->assertTrue($regularDefault->refresh()->is_default);
    }

    public function test_saving_the_default_again_keeps_its_mark(): void
    {
        $default = $this->feeStructure(isDefault: true);

        $default->update(['description' => 'Cuota general']);

        $this->assertTrue($default->refresh()->is_default);
    }

    public function test_the_database_refuses_a_second_default_written_around_the_model(): void
    {
        $this->feeStructure(isDefault: true);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('memberships_fee_structures')->insert($this->row(isDefault: true));
    }

    public function test_the_database_takes_any_number_of_fee_structures_that_are_not_the_default(): void
    {
        DB::table('memberships_fee_structures')->insert([$this->row(isDefault: false), $this->row(isDefault: false)]);

        $this->assertSame(2, FeeStructureModel::query()->count());
    }

    public function test_the_migration_keeps_only_the_latest_default_of_each_member_type_and_period(): void
    {
        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_11_07_000001_allow_one_default_fee_structure_per_member_type_and_period.php';
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, 'up') && method_exists($migration, 'down'));

        $migration->down();
        $older = $this->row(isDefault: true, validFrom: '2025-01-01');
        $latest = $this->row(isDefault: true, validFrom: '2026-01-01');
        $student = $this->row(isDefault: true, memberType: 'student', validFrom: '2024-01-01');
        DB::table('memberships_fee_structures')->insert([$older, $latest, $student]);

        $migration->up();

        $defaults = FeeStructureModel::query()->where('is_default', true)->pluck('id')->sort()->values()->all();
        $expected = collect([$latest['id'], $student['id']])->sort()->values()->all();
        $this->assertSame($expected, $defaults);
        $this->assertSame(3, FeeStructureModel::query()->count());
    }

    private function feeStructure(
        bool $isDefault,
        string $memberType = 'regular',
        string $periodType = 'calendar_year',
        string $validFrom = '2026-01-01',
    ): FeeStructureModel {
        return FeeStructureModel::create([
            'member_type' => $memberType,
            'period_type' => $periodType,
            'amount' => 30,
            'valid_from' => $validFrom,
            'is_default' => $isDefault,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        bool $isDefault,
        string $memberType = 'regular',
        string $periodType = 'calendar_year',
        string $validFrom = '2026-01-01',
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'member_type' => $memberType,
            'period_type' => $periodType,
            'amount' => 30,
            'currency' => 'EUR',
            'valid_from' => $validFrom,
            'is_default' => $isDefault,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
