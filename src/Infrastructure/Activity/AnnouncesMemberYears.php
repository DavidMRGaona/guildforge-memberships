<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use DateTimeInterface;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

/**
 * Tells the core which years of which accounts may have changed (core#774), through
 * ActivityPublisherInterface, which sends it after the commit and once per subject.
 *
 * Holds nothing: the publisher is scoped, so it is asked for on every call, never kept by an
 * observer that lives as long as the process. Nothing is announced for a record without an
 * account, and fee payments are never announced: a year does not depend on them.
 */
final class AnnouncesMemberYears
{
    /** @param  list<int>  $years */
    public function years(?string $userId, array $years): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        $publisher = app(ActivityPublisherInterface::class);

        foreach ($years as $year) {
            $publisher->recorded(MemberYearActivitySource::TYPE, MemberYears::subjectId($userId, $year));
        }
    }

    /**
     * Given years, for the account of a member record (even a deleted one).
     *
     * @param  list<int>  $years
     */
    public function memberYears(string $memberId, array $years): void
    {
        $this->years($this->accountOf($memberId), $years);
    }

    /** The years a period covers, for the account of the member it belongs to (even a deleted one). */
    public function period(string $memberId, ?string $status, string $startDate, string $endDate, ?string $expiredOn = null): void
    {
        $status = MembershipStatus::tryFrom((string) $status);

        if ($status === null) {
            return;
        }

        $this->years($this->accountOf($memberId), MemberYears::covered($status, $startDate, $endDate, ActivityDays::today(), $expiredOn));
    }

    /**
     * A period that changed: the years it covered and covers, unless they are the same, for the
     * same account and from the same day (expiring a period, or editing its notes, changes nothing).
     */
    public function periodChanged(
        string $previousMemberId,
        string $previousStatus,
        string $previousStart,
        string $previousEnd,
        ?string $previousExpiredOn,
        string $memberId,
        string $status,
        string $start,
        string $end,
        ?string $expiredOn,
    ): void {
        $today = ActivityDays::today();
        $before = MembershipStatus::tryFrom($previousStatus);
        $after = MembershipStatus::tryFrom($status);
        $yearsBefore = $before === null ? [] : MemberYears::covered($before, $previousStart, $previousEnd, $today, $previousExpiredOn);
        $yearsAfter = $after === null ? [] : MemberYears::covered($after, $start, $end, $today, $expiredOn);

        if ($previousMemberId === $memberId && $yearsBefore === $yearsAfter && ($yearsAfter === [] || substr($previousStart, 0, 10) === substr($start, 0, 10))) {
            return;
        }

        // From the same day of the same record only the years that began or stopped counting change
        if ($previousMemberId === $memberId && substr($previousStart, 0, 10) === substr($start, 0, 10)) {
            $this->years($this->accountOf($memberId), array_values(array_unique([...array_diff($yearsBefore, $yearsAfter), ...array_diff($yearsAfter, $yearsBefore)])));

            return;
        }

        $this->years($this->accountOf($previousMemberId), $yearsBefore);
        $this->years($this->accountOf($memberId), $yearsAfter);
    }

    /**
     * Every year the member counts for, oldest first: the ones their periods cover and, while the
     * record is active and of a permanent type, every one since they joined.
     *
     * @return list<int>
     */
    public function yearsOfMember(string $memberId): array
    {
        $today = ActivityDays::today();
        $years = [];

        foreach (MembershipModel::query()->where('member_id', $memberId)->get(['status', 'start_date', 'end_date', 'expired_at']) as $period) {
            array_push($years, ...MemberYears::covered($period->status, $period->start_date->toDateString(), $period->end_date->toDateString(), $today, ActivityDays::of($period->expired_at)));
        }

        $member = MemberModel::withTrashed()->find($memberId, ['id', 'status', 'member_type', 'joined_at']);

        if ($member instanceof MemberModel && $member->status === MemberStatus::Active && $this->isPermanent($member->member_type)) {
            array_push($years, ...MemberYears::permanent(ActivityDays::of($member->joined_at), $today));
        }

        $years = array_values(array_unique($years));
        sort($years);

        return $years;
    }

    /**
     * Every year any of the account's records counts for, oldest first.
     *
     * @return list<int>
     */
    public function yearsOfAccount(string $userId): array
    {
        $years = [];

        foreach (MemberModel::query()->where('user_id', $userId)->pluck('id') as $memberId) {
            array_push($years, ...$this->yearsOfMember((string) $memberId));
        }

        $years = array_values(array_unique($years));
        sort($years);

        return $years;
    }

    /**
     * The status, type or join day of a record changed: it may have been, or may now be, a
     * permanent member, whose years begin the day they joined.
     */
    public function memberStateChanged(MemberModel $member): void
    {
        $previousType = $member->getOriginal('member_type');

        if ($this->isPermanent($member->member_type) || ($previousType instanceof MemberType && $this->isPermanent($previousType))) {
            $years = $this->yearsOfMember($member->id);
            $previousJoined = $member->getOriginal('joined_at');
            $joined = min(
                ActivityDays::of($member->joined_at) ?? '9999-12-31',
                $previousJoined instanceof DateTimeInterface ? (ActivityDays::of($previousJoined) ?? '9999-12-31') : '9999-12-31',
            );

            array_push($years, ...MemberYears::permanent($joined === '9999-12-31' ? null : $joined, ActivityDays::today()));
            $years = array_values(array_unique($years));
            sort($years);

            $this->years($member->user_id, $years);
        }
    }

    public function member(MemberModel $member, ?string $userId): void
    {
        $this->years($userId, $this->yearsOfMember($member->id));
    }

    private function accountOf(string $memberId): ?string
    {
        $userId = MemberModel::withTrashed()->whereKey($memberId)->value('user_id');

        return is_string($userId) ? $userId : null;
    }

    private function isPermanent(MemberType $type): bool
    {
        return in_array($type, app(MembershipSettingsReader::class)->permanentMemberTypes(), true);
    }
}
