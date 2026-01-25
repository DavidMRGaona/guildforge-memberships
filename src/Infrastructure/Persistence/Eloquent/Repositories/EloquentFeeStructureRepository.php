<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\FeeStructure;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Exceptions\FeeStructureNotFoundException;
use Modules\Memberships\Domain\Repositories\FeeStructureRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\FeeStructureId;
use Modules\Memberships\Domain\ValueObjects\Money;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;

final readonly class EloquentFeeStructureRepository implements FeeStructureRepositoryInterface
{
    public function save(FeeStructure $feeStructure): void
    {
        FeeStructureModel::query()->updateOrCreate(
            ['id' => $feeStructure->id->value],
            $this->toArray($feeStructure),
        );
    }

    public function find(FeeStructureId $id): ?FeeStructure
    {
        $model = FeeStructureModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findOrFail(FeeStructureId $id): FeeStructure
    {
        $feeStructure = $this->find($id);

        if ($feeStructure === null) {
            throw FeeStructureNotFoundException::notFoundById($id->value);
        }

        return $feeStructure;
    }

    /**
     * @return array<FeeStructure>
     */
    public function findByMemberType(MemberType $memberType): array
    {
        return FeeStructureModel::query()
            ->where('member_type', $memberType->value)
            ->get()
            ->map(fn (FeeStructureModel $model): FeeStructure => $this->toEntity($model))
            ->all();
    }

    public function getCurrentStructure(MemberType $memberType, MembershipPeriodType $periodType): ?FeeStructure
    {
        $today = new DateTimeImmutable();

        $model = FeeStructureModel::query()
            ->where('member_type', $memberType->value)
            ->where('period_type', $periodType->value)
            ->where('valid_from', '<=', $today->format('Y-m-d'))
            ->where(function ($query) use ($today): void {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $today->format('Y-m-d'));
            })
            ->orderByDesc('valid_from')
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function getDefaultStructure(MemberType $memberType, MembershipPeriodType $periodType): ?FeeStructure
    {
        $model = FeeStructureModel::query()
            ->where('member_type', $memberType->value)
            ->where('period_type', $periodType->value)
            ->where('is_default', true)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function delete(FeeStructureId $id): void
    {
        FeeStructureModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return array<FeeStructure>
     */
    public function all(): array
    {
        return FeeStructureModel::query()
            ->get()
            ->map(fn (FeeStructureModel $model): FeeStructure => $this->toEntity($model))
            ->all();
    }

    public function toEntity(FeeStructureModel $model): FeeStructure
    {
        return new FeeStructure(
            id: new FeeStructureId($model->id),
            memberType: $model->member_type,
            periodType: $model->period_type,
            amount: Money::fromAmount((string) $model->amount, $model->currency),
            prorationRules: $model->proration_rules,
            validFrom: new DateTimeImmutable($model->valid_from->toDateString()),
            validUntil: $model->valid_until !== null
                ? new DateTimeImmutable($model->valid_until->toDateString())
                : null,
            description: $model->description,
            isDefault: $model->is_default,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(FeeStructure $feeStructure): array
    {
        return [
            'id' => $feeStructure->id->value,
            'member_type' => $feeStructure->memberType->value,
            'period_type' => $feeStructure->periodType->value,
            'amount' => $feeStructure->amount->toAmount(),
            'currency' => $feeStructure->amount->currency,
            'proration_rules' => $feeStructure->prorationRules,
            'valid_from' => $feeStructure->validFrom->format('Y-m-d'),
            'valid_until' => $feeStructure->validUntil?->format('Y-m-d'),
            'description' => $feeStructure->description,
            'is_default' => $feeStructure->isDefault,
        ];
    }
}
