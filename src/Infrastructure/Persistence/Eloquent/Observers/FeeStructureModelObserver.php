<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers;

use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;

/**
 * One default fee structure per member type and period type: saving one as the default takes
 * the mark from the previous one, before the write, so the partial unique index never sees two.
 * The panel pages wrap the save in a transaction, so a failed write keeps the old mark.
 *
 * Mass updates (FeeStructureModel::query()->update()) fire no model events: the index refuses
 * a second default written that way.
 */
final readonly class FeeStructureModelObserver
{
    public function saving(FeeStructureModel $feeStructure): void
    {
        if (! $feeStructure->is_default) {
            return;
        }

        if ($feeStructure->exists && ! $feeStructure->isDirty(['is_default', 'member_type', 'period_type'])) {
            return;
        }

        FeeStructureModel::defaultsFor(
            $feeStructure->member_type,
            $feeStructure->period_type,
            $feeStructure->exists ? $feeStructure->getKey() : null,
        )->update(['is_default' => false]);
    }
}
