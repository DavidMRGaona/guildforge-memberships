<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Exceptions\MemberNotFoundException;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;

final readonly class EloquentMemberRepository implements MemberRepositoryInterface
{
    public function save(Member $member): void
    {
        MemberModel::query()->updateOrCreate(
            ['id' => $member->id->value],
            $this->toArray($member),
        );
    }

    public function find(MemberId $id): ?Member
    {
        $model = MemberModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findOrFail(MemberId $id): Member
    {
        $member = $this->find($id);

        if ($member === null) {
            throw MemberNotFoundException::notFoundById($id->value);
        }

        return $member;
    }

    public function findByMemberNumber(MemberNumber $memberNumber): ?Member
    {
        $model = MemberModel::query()
            ->where('member_number', $memberNumber->value)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByEmail(string $email): ?Member
    {
        $model = MemberModel::query()
            ->where('email', $email)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByUserId(string $userId): ?Member
    {
        $model = MemberModel::query()
            ->where('user_id', $userId)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function delete(MemberId $id): void
    {
        MemberModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return array<Member>
     */
    public function getActiveMembers(): array
    {
        return $this->getByStatus(MemberStatus::Active);
    }

    /**
     * @return array<Member>
     */
    public function getByStatus(MemberStatus $status): array
    {
        return MemberModel::query()
            ->where('status', $status->value)
            ->get()
            ->map(fn (MemberModel $model): Member => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Member>
     */
    public function searchMembers(string $query): array
    {
        $searchTerm = '%' . $query . '%';

        return MemberModel::query()
            ->where(function ($q) use ($searchTerm): void {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm);
            })
            ->get()
            ->map(fn (MemberModel $model): Member => $this->toEntity($model))
            ->all();
    }

    public function existsByEmail(string $email): bool
    {
        return MemberModel::query()
            ->where('email', $email)
            ->exists();
    }

    public function existsByMemberNumber(MemberNumber $memberNumber): bool
    {
        return MemberModel::query()
            ->where('member_number', $memberNumber->value)
            ->exists();
    }

    public function getNextMemberNumber(int $year): MemberNumber
    {
        $pattern = sprintf('%04d-%%', $year);

        $lastMember = MemberModel::query()
            ->where('member_number', 'like', $pattern)
            ->orderByDesc('member_number')
            ->first();

        if ($lastMember === null) {
            return MemberNumber::generate($year, 1);
        }

        $lastNumber = new MemberNumber($lastMember->member_number);
        $nextSequence = $lastNumber->sequence() + 1;

        return MemberNumber::generate($year, $nextSequence);
    }

    /**
     * @return array<Member>
     */
    public function all(): array
    {
        return MemberModel::query()
            ->get()
            ->map(fn (MemberModel $model): Member => $this->toEntity($model))
            ->all();
    }

    public function count(): int
    {
        return MemberModel::query()->count();
    }

    public function countByStatus(MemberStatus $status): int
    {
        return MemberModel::query()
            ->where('status', $status->value)
            ->count();
    }

    public function countWithActiveMembership(): int
    {
        return MemberModel::query()
            ->whereHas('memberships', function ($query): void {
                $query->where('status', \Modules\Memberships\Domain\Enums\MembershipStatus::Active->value);
            })
            ->count();
    }

    public function toEntity(MemberModel $model): Member
    {
        return new Member(
            id: new MemberId($model->id),
            memberNumber: new MemberNumber($model->member_number),
            firstName: $model->first_name,
            lastName: $model->last_name,
            email: $model->email,
            phone: $model->phone,
            birthDate: $model->birth_date !== null
                ? new DateTimeImmutable($model->birth_date->toDateString())
                : null,
            address: $model->address,
            memberType: $model->member_type,
            status: $model->status,
            userId: $model->user_id,
            notes: $model->notes,
            joinedAt: new DateTimeImmutable($model->joined_at->toDateTimeString()),
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Member $member): array
    {
        return [
            'id' => $member->id->value,
            'member_number' => $member->memberNumber->value,
            'first_name' => $member->firstName,
            'last_name' => $member->lastName,
            'email' => $member->email,
            'phone' => $member->phone,
            'birth_date' => $member->birthDate?->format('Y-m-d'),
            'address' => $member->address,
            'member_type' => $member->memberType->value,
            'status' => $member->status->value,
            'user_id' => $member->userId,
            'notes' => $member->notes,
            'joined_at' => $member->joinedAt->format('Y-m-d H:i:s'),
        ];
    }
}
