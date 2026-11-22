<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;

/**
 * Years as a member (core#774): one subject per account and calendar year of the display zone in
 * which a period of a member record linked to the account was in force (see MemberYears for the
 * definition). Several records or overlapping periods of one account are still one subject per
 * year. Records without an account and deleted records are never reported.
 *
 * Membership is sensitive, so the subjects only feed counts: describe() gives a neutral title and
 * no link, and only to the account itself or to whoever may manage members. Never reads the
 * signed-in user outside describe(): the same answer in a queued job as in a request.
 */
final readonly class MemberYearActivitySource implements ActivitySourceInterface
{
    /** Stored by consumers with every fact: never changes. */
    public const string TYPE = 'memberships.year';

    private const int ACCOUNTS_PER_QUERY = 100;

    private const string MANAGE_PERMISSION = 'memberships:members.view_any';

    public function type(): string
    {
        return self::TYPE;
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::MembershipYear;
    }

    public function label(): string
    {
        return __('memberships::memberships.activity.year_label');
    }

    public function facts(string $subjectId): array
    {
        $parsed = MemberYears::parseSubjectId($subjectId);

        if ($parsed === null) {
            return [];
        }

        [$userId, $year] = $parsed;
        $day = $this->firstDaysOf([$userId])[$userId][$year] ?? null;

        return $day === null ? [] : [self::fact($userId, $day)];
    }

    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        $after = $cursor === null ? null : MemberYearCursor::read($cursor);
        $limit = max(1, min($limit, ActivityHistoryPageDTO::MAX_LIMIT));

        $subjects = [];
        $hasMore = false;
        $from = $after[0] ?? null;
        $inclusive = true;

        while (true) {
            $userIds = $this->accountsFrom($from, $inclusive);

            if ($userIds === []) {
                break;
            }

            $days = $this->firstDaysOf($userIds);

            foreach ($userIds as $userId) {
                foreach ($days[$userId] ?? [] as $year => $day) {
                    if ($after !== null && $userId === $after[0] && $year <= $after[1]) {
                        continue;
                    }

                    if (count($subjects) === $limit) {
                        $hasMore = true;
                        break 3;
                    }

                    $subjects[] = [$userId, $year, $day];
                }
            }

            $from = array_last($userIds);
            $inclusive = false;
        }

        // Another subject follows only when the page is full, so it has a last one
        $nextCursor = null;

        if ($hasMore) {
            $last = $subjects[count($subjects) - 1];
            $nextCursor = MemberYearCursor::after($last[0], $last[1]);
        }

        return new ActivityHistoryPageDTO(
            subjects: array_map(
                static fn (array $subject): ActivitySubjectFactsDTO => new ActivitySubjectFactsDTO(MemberYears::subjectId($subject[0], $subject[1]), [self::fact($subject[0], $subject[2])]),
                $subjects,
            ),
            nextCursor: $nextCursor,
        );
    }

    /**
     * Only to the account the year belongs to and to whoever may manage members, and only for
     * years that count. A neutral title, never a link: nobody is led from a badge to a record.
     */
    public function describe(array $subjectIds, ?Authenticatable $viewer): array
    {
        if (! $viewer instanceof Authenticatable) {
            return [];
        }

        $wanted = [];

        foreach ($subjectIds as $subjectId) {
            $parsed = MemberYears::parseSubjectId((string) $subjectId);

            if ($parsed !== null) {
                $wanted[$parsed[0]][$parsed[1]] = true;
            }
        }

        if ($wanted === []) {
            return [];
        }

        $viewerId = strtolower((string) $viewer->getAuthIdentifier());
        $manages = app(AuthorizationServiceInterface::class)->can($viewer, self::MANAGE_PERMISSION);
        $visible = array_keys($wanted);

        if (! $manages) {
            $visible = array_values(array_filter($visible, static fn (string $userId): bool => $userId === $viewerId));
        }

        $described = [];

        foreach ($this->firstDaysOf($visible) as $userId => $days) {
            foreach (array_keys($days) as $year) {
                if (isset($wanted[$userId][$year])) {
                    $id = MemberYears::subjectId($userId, $year);
                    $described[$id] = new ActivitySubjectDTO($id, __('memberships::memberships.activity.year_title', ['year' => $year]));
                }
            }
        }

        return $described;
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, array<int, string>> account => year => first day in force
     */
    private function firstDaysOf(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $today = CalendarDate::today()->format('Y-m-d');
        $periods = [];

        foreach ($this->counting($today)->whereIn('members.user_id', $userIds)->get($this->columns()) as $period) {
            $periods[strtolower((string) $period->getAttribute('account_id'))][] = [
                $period->status,
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ];
        }

        return array_map(static fn (array $own): array => MemberYears::firstDays($own, $today), $periods);
    }

    /**
     * The next accounts with a period that counts, in the database's order.
     *
     * @return list<string>
     */
    private function accountsFrom(?string $from, bool $inclusive): array
    {
        $query = $this->counting(CalendarDate::today()->format('Y-m-d'))
            ->when($from !== null, static fn (Builder $query): Builder => $query->where('members.user_id', $inclusive ? '>=' : '>', $from))
            ->distinct()
            ->orderBy('members.user_id')
            ->limit(self::ACCOUNTS_PER_QUERY);

        return array_values(array_map(
            static fn (mixed $userId): string => strtolower((string) $userId),
            $query->pluck('members.user_id')->all(),
        ));
    }

    /**
     * Periods in force of records that are not deleted and have an account, already begun.
     *
     * @return Builder<MembershipModel>
     */
    private function counting(string $today): Builder
    {
        return MembershipModel::query()
            ->join('memberships_members as members', 'members.id', '=', 'memberships_memberships.member_id')
            ->whereNull('members.deleted_at')
            ->whereNotNull('members.user_id')
            ->whereIn('memberships_memberships.status', [MembershipStatus::Active->value, MembershipStatus::Expired->value])
            ->whereDate('memberships_memberships.start_date', '<=', $today);
    }

    /**
     * @return list<string>
     */
    private function columns(): array
    {
        return [
            'memberships_memberships.status',
            'memberships_memberships.start_date',
            'memberships_memberships.end_date',
            'members.user_id as account_id',
        ];
    }

    private static function fact(string $userId, string $day): ActivityFactDTO
    {
        return new ActivityFactDTO($userId, ActivityRole::Member, CalendarDate::parse($day));
    }
}
