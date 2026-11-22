<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Profiles\Services\ProfileMembershipDisplayInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

/**
 * Years as a member (core#774): one subject per account and calendar year of the display zone in
 * which a period of a member record linked to the account was in force (see MemberYears for the
 * definition). Several records or overlapping periods of one account are still one subject per
 * year. Records without an account and deleted records are never reported.
 *
 * A member of a permanent type (honorary by default, a module setting) holds the role without any
 * period while the record is active, so as long as it is active each calendar year from the day
 * they joined counts as well, whether or not they have periods.
 *
 * Membership is sensitive: the site shows that somebody is a member only when they turn on the
 * «Socio» badge of their profile, so facts() and history() report only the accounts that show it
 * (ProfileMembershipDisplayInterface; if that cannot be read its exception goes up, so the core
 * keeps what it had) and the module republishes an account when its answer may have changed. The
 * subjects only feed counts: describe() gives a neutral title and no link, and only to the account
 * itself or to whoever may manage members. Never reads the signed-in user outside describe(): the
 * same answer in a queued job as in a request.
 */
final readonly class MemberYearActivitySource implements ActivitySourceInterface
{
    /**
     * @param  int  $accountsPerQuery  how many accounts the history reads at once
     * @param  int  $maxBatchesPerCall  how many of those batches one history call reads at most, so a site where
     *                                  most accounts hide their membership never reads the whole table in one call
     */
    public function __construct(
        private ProfileMembershipDisplayInterface $display,
        private int $accountsPerQuery = 100,
        private int $maxBatchesPerCall = 20,
    ) {}

    /** Stored by consumers with every fact: never changes. */
    public const string TYPE = 'memberships.year';

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

        if ($this->shown([$userId]) === []) {
            return [];
        }

        $day = $this->firstDaysOf([$userId], ActivityDays::today())[$userId][$year] ?? null;

        return $day === null ? [] : [self::fact($userId, $day)];
    }

    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        $after = $cursor === null ? null : MemberYearCursor::read($cursor);
        $limit = max(1, min($limit, ActivityHistoryPageDTO::MAX_LIMIT));
        $today = ActivityDays::today();

        $subjects = [];
        $hasMore = false;
        $from = $after[0] ?? null;
        // A cursor with the year 9999 says every year of that account is done (see below)
        $inclusive = ($after[1] ?? 0) < 9999;
        $batches = 0;
        $capped = false;

        while (true) {
            $batch = $this->accountsFrom($from, $inclusive, $today);

            if ($batch === []) {
                break;
            }

            $userIds = $this->shown($batch);
            $days = $this->firstDaysOf($userIds, $today);

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

            $from = array_last($batch);
            $inclusive = false;

            if (++$batches >= $this->maxBatchesPerCall) {
                $capped = true;

                break;
            }
        }

        // Another subject follows only when the page is full, so it has a last one
        $nextCursor = null;

        $last = array_last($subjects);

        if ($hasMore && $last !== null) {
            $nextCursor = MemberYearCursor::after($last[0], $last[1]);
        } elseif ($capped) {
            // The call read all it may: carry on after the last account read (every one of its years is
            // done), even when none of them showed anything, which the contract allows
            $nextCursor = MemberYearCursor::after($from, 9999);
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

        foreach ($this->firstDaysOf($visible, ActivityDays::today()) as $userId => $days) {
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
     * The accounts that show their membership, in the order given. A failure is not an answer:
     * the exception goes up, never «nobody shows it».
     *
     * @param  list<string>  $userIds
     * @return list<string>
     */
    private function shown(array $userIds): array
    {
        $shown = [];

        foreach (array_chunk($userIds, ProfileMembershipDisplayInterface::MAX_IDS) as $chunk) {
            $shown += $this->display->showsMembership($chunk);
        }

        return array_values(array_filter($userIds, static fn (string $userId): bool => ($shown[$userId] ?? false) === true));
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, array<int, string>> account => year => first day in force
     */
    private function firstDaysOf(array $userIds, string $today): array
    {
        if ($userIds === []) {
            return [];
        }

        $periods = [];

        foreach ($this->counting($today)->whereIn('members.user_id', $userIds)->get($this->columns()) as $period) {
            $periods[strtolower((string) $period->getAttribute('account_id'))][] = [
                $period->status,
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
                ActivityDays::of($period->expired_at),
            ];
        }

        $since = [];

        foreach (MemberModel::query()
            ->whereIn('user_id', $userIds)
            ->where('status', MemberStatus::Active->value)
            ->whereIn('member_type', self::permanentTypes())
            ->get(['user_id', 'joined_at']) as $member) {
            $account = strtolower((string) $member->user_id);
            $day = ActivityDays::of($member->joined_at);

            if ($day !== null && (! isset($since[$account]) || $day < $since[$account])) {
                $since[$account] = $day;
            }
        }

        $days = [];

        foreach (array_unique([...array_keys($periods), ...array_keys($since)]) as $account) {
            $days[$account] = MemberYears::firstDays($periods[$account] ?? [], $today, $since[$account] ?? null);
        }

        return $days;
    }

    /**
     * The next accounts with a period that counts or a permanent member that is active, in the
     * database's order.
     *
     * @return list<string>
     */
    private function accountsFrom(?string $from, bool $inclusive, string $today): array
    {
        $endOfToday = app(TimezoneServiceInterface::class)->displayDayRange($today)[1];

        $query = MemberModel::query()
            ->whereNotNull('user_id')
            ->when($from !== null, static fn (Builder $query): Builder => $query->where('user_id', $inclusive ? '>=' : '>', $from))
            ->where(static fn (Builder $query): Builder => $query
                ->whereHas('memberships', static fn (Builder $period): Builder => $period
                    ->whereIn('status', [MembershipStatus::Active->value, MembershipStatus::Expired->value])
                    ->whereDate('start_date', '<=', $today))
                ->orWhere(static fn (Builder $permanent): Builder => $permanent
                    ->where('status', MemberStatus::Active->value)
                    ->whereIn('member_type', self::permanentTypes())
                    ->where('joined_at', '<', $endOfToday)))
            ->distinct()
            ->orderBy('user_id')
            ->limit($this->accountsPerQuery);

        return array_values(array_map(
            static fn (mixed $userId): string => strtolower((string) $userId),
            $query->pluck('user_id')->all(),
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
            'memberships_memberships.expired_at',
            'members.user_id as account_id',
        ];
    }

    /**
     * @return list<string> the member types that hold the role without a period (module setting)
     */
    private static function permanentTypes(): array
    {
        return array_map(
            static fn (MemberType $type): string => $type->value,
            app(MembershipSettingsReader::class)->permanentMemberTypes(),
        );
    }

    private static function fact(string $userId, string $day): ActivityFactDTO
    {
        return new ActivityFactDTO($userId, ActivityRole::Member, CalendarDate::parse($day));
    }
}
