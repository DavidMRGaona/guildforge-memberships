<?php

declare(strict_types=1);

namespace Modules\Memberships\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Infrastructure\Activity\AnnouncesMemberYears;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;

/**
 * Years as a member (core#774) grow with the calendar, not with a write: a year counts from the
 * day a period in force reaches it, so on that day nobody changes anything for the core to hear.
 * Once a day this tells it of the periods that reach a new year today: the ones that start today
 * and, on 1 January, the ones that run into the new year. Telling it twice is harmless, and a day
 * the scheduler does not run is made up by the core's next full read of the history.
 */
final class AnnounceMemberYears extends Command
{
    /** @var string */
    protected $signature = 'memberships:announce-years';

    /** @var string */
    protected $description = 'Tell the activity contract of the years as a member that begin today';

    public function handle(AnnouncesMemberYears $years): int
    {
        $todayDate = CalendarDate::today();
        $today = $todayDate->format('Y-m-d');
        $year = (int) $todayDate->format('Y');
        $newYear = $todayDate->format('m-d') === '01-01';

        $announced = 0;

        MembershipModel::query()
            ->whereIn('status', [MembershipStatus::Active->value, MembershipStatus::Expired->value])
            ->whereDate('end_date', '>=', $today)
            ->where(static fn (Builder $query): Builder => $query
                ->whereDate('start_date', $today)
                ->when($newYear, static fn (Builder $query): Builder => $query->orWhereDate('start_date', '<', $today)))
            ->orderBy('id')
            ->chunkById(200, function ($periods) use ($years, $year, &$announced): void {
                foreach ($periods as $period) {
                    $years->memberYears($period->member_id, [$year]);
                    $announced++;
                }
            });

        $this->components->info("Periods announced: {$announced}");

        return self::SUCCESS;
    }
}
