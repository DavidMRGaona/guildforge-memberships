<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources\FeeStructureResource\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Support\Number;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;

/**
 * Says which fee structure stopped being the default when the one being saved takes the mark
 * (FeeStructureModelObserver moves it). The page saves in a transaction, so a failed write
 * keeps the old mark.
 */
trait AnnouncesTheMovedDefault
{
    protected ?bool $hasDatabaseTransactions = true;

    private ?FeeStructureModel $replacedDefault = null;

    /**
     * Looks up, before the write, the default that the submitted data would replace.
     *
     * @param  array<string, mixed>  $data
     */
    private function rememberTheReplacedDefault(array $data, ?string $exceptId): void
    {
        $memberType = $data['member_type'] ?? null;
        $periodType = $data['period_type'] ?? null;

        $known = ($memberType instanceof MemberType || is_string($memberType))
            && ($periodType instanceof MembershipPeriodType || is_string($periodType));

        $this->replacedDefault = ($data['is_default'] ?? false) && $known
            ? FeeStructureModel::defaultsFor($memberType, $periodType, $exceptId)->first()
            : null;
    }

    private function announceTheReplacedDefault(): void
    {
        $old = $this->replacedDefault;

        if (! $old instanceof FeeStructureModel) {
            return;
        }

        Notification::make()
            ->title(__('memberships::memberships.fee_structure.default_moved_title'))
            ->body(__('memberships::memberships.fee_structure.default_moved', [
                'amount' => Number::currency((float) $old->amount, $old->currency, app()->getLocale()),
                'date' => $old->valid_from->format('d/m/Y'),
                'member_type' => __('memberships::memberships.enums.member_type.'.$old->member_type->value),
                'period_type' => __('memberships::memberships.enums.membership_period_type.'.$old->period_type->value),
            ]))
            ->info()
            ->send();
    }
}
