<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;

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
    public function period(string $memberId, ?string $status, string $startDate, string $endDate): void
    {
        $status = MembershipStatus::tryFrom((string) $status);

        if ($status === null) {
            return;
        }

        $this->years($this->accountOf($memberId), MemberYears::covered($status, $startDate, $endDate, $this->today()));
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
        string $memberId,
        string $status,
        string $start,
        string $end,
    ): void {
        $today = $this->today();
        $before = MembershipStatus::tryFrom($previousStatus);
        $after = MembershipStatus::tryFrom($status);
        $yearsBefore = $before === null ? [] : MemberYears::covered($before, $previousStart, $previousEnd, $today);
        $yearsAfter = $after === null ? [] : MemberYears::covered($after, $start, $end, $today);

        if ($previousMemberId === $memberId && $yearsBefore === $yearsAfter && ($yearsAfter === [] || substr($previousStart, 0, 10) === substr($start, 0, 10))) {
            return;
        }

        $this->years($this->accountOf($previousMemberId), $yearsBefore);
        $this->years($this->accountOf($memberId), $yearsAfter);
    }

    /**
     * Every year any period of the member covers, oldest first.
     *
     * @return list<int>
     */
    public function yearsOfMember(string $memberId): array
    {
        $today = $this->today();
        $years = [];

        foreach (MembershipModel::query()->where('member_id', $memberId)->get(['status', 'start_date', 'end_date']) as $period) {
            array_push($years, ...MemberYears::covered($period->status, $period->start_date->toDateString(), $period->end_date->toDateString(), $today));
        }

        $years = array_values(array_unique($years));
        sort($years);

        return $years;
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

    private function today(): string
    {
        return CalendarDate::today()->format('Y-m-d');
    }
}
