<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Membership;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Exceptions\MembershipNotFoundException;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;

final readonly class EloquentMembershipRepository implements MembershipRepositoryInterface
{
    public function save(Membership $membership): void
    {
        MembershipModel::query()->updateOrCreate(
            ['id' => $membership->id->value],
            $this->toArray($membership),
        );
    }

    public function find(MembershipId $id): ?Membership
    {
        $model = MembershipModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findOrFail(MembershipId $id): Membership
    {
        $membership = $this->find($id);

        if ($membership === null) {
            throw MembershipNotFoundException::notFoundById($id->value);
        }

        return $membership;
    }

    /**
     * @return array<Membership>
     */
    public function findByMemberId(MemberId $memberId): array
    {
        return MembershipModel::query()
            ->where('member_id', $memberId->value)
            ->get()
            ->map(fn (MembershipModel $model): Membership => $this->toEntity($model))
            ->all();
    }

    public function findActiveMembership(MemberId $memberId): ?Membership
    {
        $model = MembershipModel::query()
            ->where('member_id', $memberId->value)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    /**
     * @return array<Membership>
     */
    public function getExpiringMemberships(int $daysFromNow): array
    {
        $today = new DateTimeImmutable();
        $futureDate = $today->modify("+{$daysFromNow} days");

        return MembershipModel::query()
            ->where('status', MembershipStatus::Active->value)
            ->whereBetween('end_date', [$today->format('Y-m-d'), $futureDate->format('Y-m-d')])
            ->get()
            ->map(fn (MembershipModel $model): Membership => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Membership>
     */
    public function getExpiredMemberships(): array
    {
        $today = new DateTimeImmutable();

        return MembershipModel::query()
            ->where(function ($query) use ($today): void {
                $query->where('status', MembershipStatus::Expired->value)
                    ->orWhere(function ($q) use ($today): void {
                        $q->where('end_date', '<', $today->format('Y-m-d'))
                            ->where('status', '!=', MembershipStatus::Cancelled->value);
                    });
            })
            ->get()
            ->map(fn (MembershipModel $model): Membership => $this->toEntity($model))
            ->all();
    }

    public function delete(MembershipId $id): void
    {
        MembershipModel::query()->where('id', $id->value)->delete();
    }

    public function hasOverlappingMembership(
        MemberId $memberId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?MembershipId $excludeId = null,
    ): bool {
        $query = MembershipModel::query()
            ->where('member_id', $memberId->value)
            ->where('status', '!=', MembershipStatus::Cancelled->value)
            ->where(function ($q) use ($startDate, $endDate): void {
                // Overlap check: existing membership overlaps with proposed dates
                $q->where(function ($inner) use ($startDate, $endDate): void {
                    $inner->where('start_date', '<=', $endDate->format('Y-m-d'))
                        ->where('end_date', '>=', $startDate->format('Y-m-d'));
                });
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId->value);
        }

        return $query->exists();
    }

    /**
     * @return array<Membership>
     */
    public function all(): array
    {
        return MembershipModel::query()
            ->get()
            ->map(fn (MembershipModel $model): Membership => $this->toEntity($model))
            ->all();
    }

    public function toEntity(MembershipModel $model): Membership
    {
        return new Membership(
            id: new MembershipId($model->id),
            memberId: new MemberId($model->member_id),
            periodType: $model->period_type,
            startDate: new DateTimeImmutable($model->start_date->toDateString()),
            endDate: new DateTimeImmutable($model->end_date->toDateString()),
            status: $model->status,
            activatedAt: $model->activated_at !== null
                ? new DateTimeImmutable($model->activated_at->toDateTimeString())
                : null,
            cancelledAt: $model->cancelled_at !== null
                ? new DateTimeImmutable($model->cancelled_at->toDateTimeString())
                : null,
            notes: $model->notes,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Membership $membership): array
    {
        return [
            'id' => $membership->id->value,
            'member_id' => $membership->memberId->value,
            'period_type' => $membership->periodType->value,
            'start_date' => $membership->startDate->format('Y-m-d'),
            'end_date' => $membership->endDate->format('Y-m-d'),
            'status' => $membership->status->value,
            'activated_at' => $membership->activatedAt?->format('Y-m-d H:i:s'),
            'cancelled_at' => $membership->cancelledAt?->format('Y-m-d H:i:s'),
            'notes' => $membership->notes,
        ];
    }
}
