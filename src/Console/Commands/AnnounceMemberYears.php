<?php

declare(strict_types=1);

namespace Modules\Memberships\Console\Commands;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Infrastructure\Activity\ActivityDays;
use Modules\Memberships\Infrastructure\Activity\AnnouncesMemberYears;
use Modules\Memberships\Infrastructure\Activity\MemberYears;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

/**
 * Years as a member (core#774) grow with the calendar, not with a write: a year counts from the
 * day a period in force (or a permanent member) reaches it, so on that day nobody changes anything
 * for the core to hear. Once a day this tells it of the years that began counting since the last
 * day it announced: for every period and permanent member, the years they count for today and did
 * not on that day. A day the scheduler missed is made up by the next run, and telling it twice is
 * harmless.
 */
final class AnnounceMemberYears extends Command
{
    /** The last display day announced, kept as a setting of the module. */
    public const string LAST_DAY_SETTING = 'memberships_years_announced_until';

    /** @var string */
    protected $signature = 'memberships:announce-years';

    /** @var string */
    protected $description = 'Tell the activity contract of the years as a member that began since the last run';

    public function handle(AnnouncesMemberYears $years, SettingsServiceInterface $settings, MembershipSettingsReader $memberSettings, TimezoneServiceInterface $timezone): int
    {
        $today = ActivityDays::today();
        $last = $this->lastDay($settings, $today);

        /** @var array<string, array<int, true>> $began */
        $began = [];

        $this->periods($today, $last, $began);
        $this->permanentMembers($today, $last, $memberSettings, $timezone, $began);

        foreach ($began as $account => $accountYears) {
            $years->years($account, array_keys($accountYears));
        }

        $settings->set(self::LAST_DAY_SETTING, $today);
        $this->components->info('Accounts announced: '.count($began));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<int, true>>  $began
     */
    private function periods(string $today, string $last, array &$began): void
    {
        MembershipModel::query()
            ->select(['memberships_memberships.*', 'members.user_id as account_id'])
            ->join('memberships_members as members', 'members.id', '=', 'memberships_memberships.member_id')
            ->whereNull('members.deleted_at')
            ->whereNotNull('members.user_id')
            ->whereIn('memberships_memberships.status', [MembershipStatus::Active->value, MembershipStatus::Expired->value])
            ->whereDate('memberships_memberships.start_date', '<=', $today)
            ->whereDate('memberships_memberships.end_date', '>', $last)
            ->chunkById(200, function ($periods) use ($today, $last, &$began): void {
                foreach ($periods as $period) {
                    $expiredOn = ActivityDays::of($period->expired_at);
                    $start = $period->start_date->toDateString();
                    $end = $period->end_date->toDateString();

                    $new = array_diff(
                        MemberYears::covered($period->status, $start, $end, $today, $expiredOn),
                        MemberYears::covered($period->status, $start, $end, $last, $expiredOn),
                    );

                    foreach ($new as $year) {
                        $began[strtolower((string) $period->getAttribute('account_id'))][$year] = true;
                    }
                }
            }, 'memberships_memberships.id', 'id');
    }

    /**
     * @param  array<string, array<int, true>>  $began
     */
    private function permanentMembers(string $today, string $last, MembershipSettingsReader $settings, TimezoneServiceInterface $timezone, array &$began): void
    {
        $types = array_map(static fn (MemberType $type): string => $type->value, $settings->permanentMemberTypes());

        MemberModel::query()
            ->whereNotNull('user_id')
            ->where('status', MemberStatus::Active->value)
            ->whereIn('member_type', $types)
            ->where('joined_at', '<', $timezone->displayDayRange($today)[1])
            ->chunkById(200, function ($members) use ($today, $last, &$began): void {
                foreach ($members as $member) {
                    $joined = ActivityDays::of($member->joined_at);
                    $new = array_diff(MemberYears::permanent($joined, $today), MemberYears::permanent($joined, $last));

                    foreach ($new as $year) {
                        $began[strtolower((string) $member->user_id)][$year] = true;
                    }
                }
            });
    }

    /** The day it last ran, or yesterday when it never did; never later than today. */
    private function lastDay(SettingsServiceInterface $settings, string $today): string
    {
        $stored = $settings->getStored(self::LAST_DAY_SETTING);

        if (is_string($stored) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $stored) === 1) {
            return min($stored, $today);
        }

        return (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
    }
}
