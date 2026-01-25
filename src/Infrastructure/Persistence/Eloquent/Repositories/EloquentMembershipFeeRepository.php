<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\MembershipFee;
use Modules\Memberships\Domain\Exceptions\MembershipFeeNotFoundException;
use Modules\Memberships\Domain\Repositories\MembershipFeeRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MembershipFeeId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Domain\ValueObjects\Money;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;

final readonly class EloquentMembershipFeeRepository implements MembershipFeeRepositoryInterface
{
    public function save(MembershipFee $fee): void
    {
        MembershipFeeModel::query()->updateOrCreate(
            ['id' => $fee->id->value],
            $this->toArray($fee),
        );
    }

    public function find(MembershipFeeId $id): ?MembershipFee
    {
        $model = MembershipFeeModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findOrFail(MembershipFeeId $id): MembershipFee
    {
        $fee = $this->find($id);

        if ($fee === null) {
            throw MembershipFeeNotFoundException::notFoundById($id->value);
        }

        return $fee;
    }

    /**
     * @return array<MembershipFee>
     */
    public function findByMembershipId(MembershipId $membershipId): array
    {
        return MembershipFeeModel::query()
            ->where('membership_id', $membershipId->value)
            ->get()
            ->map(fn (MembershipFeeModel $model): MembershipFee => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<MembershipFee>
     */
    public function getOverdueFees(): array
    {
        $today = new DateTimeImmutable();

        return MembershipFeeModel::query()
            ->whereNull('paid_at')
            ->where('due_date', '<', $today->format('Y-m-d'))
            ->get()
            ->map(fn (MembershipFeeModel $model): MembershipFee => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<MembershipFee>
     */
    public function getUnpaidFees(): array
    {
        return MembershipFeeModel::query()
            ->whereNull('paid_at')
            ->get()
            ->map(fn (MembershipFeeModel $model): MembershipFee => $this->toEntity($model))
            ->all();
    }

    public function delete(MembershipFeeId $id): void
    {
        MembershipFeeModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return array<MembershipFee>
     */
    public function all(): array
    {
        return MembershipFeeModel::query()
            ->get()
            ->map(fn (MembershipFeeModel $model): MembershipFee => $this->toEntity($model))
            ->all();
    }

    public function toEntity(MembershipFeeModel $model): MembershipFee
    {
        return new MembershipFee(
            id: new MembershipFeeId($model->id),
            membershipId: new MembershipId($model->membership_id),
            amount: Money::fromAmount((string) $model->amount, $model->currency),
            dueDate: new DateTimeImmutable($model->due_date->toDateString()),
            paidAt: $model->paid_at !== null
                ? new DateTimeImmutable($model->paid_at->toDateTimeString())
                : null,
            paymentMethod: $model->payment_method,
            transactionReference: $model->transaction_reference,
            notes: $model->notes,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(MembershipFee $fee): array
    {
        return [
            'id' => $fee->id->value,
            'membership_id' => $fee->membershipId->value,
            'amount' => $fee->amount->toAmount(),
            'currency' => $fee->amount->currency,
            'due_date' => $fee->dueDate->format('Y-m-d'),
            'paid_at' => $fee->paidAt?->format('Y-m-d H:i:s'),
            'payment_method' => $fee->paymentMethod?->value,
            'transaction_reference' => $fee->transactionReference,
            'notes' => $fee->notes,
        ];
    }
}
