<?php

declare(strict_types=1);

namespace Modules\Memberships\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Infrastructure\Activity\ActivityDays;
use Modules\Memberships\Infrastructure\Activity\AnnouncesMemberYears;
use Modules\Memberships\Infrastructure\Activity\MemberYears;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;

/**
 * Years as a member (core#774): when the permanent member types setting changes, the active
 * members of the types added or removed gain or lose every year since they joined, and nobody
 * edits their record for the core to hear it. Tells it of all of them, in batches.
 */
final class RepublishPermanentMemberYears implements ShouldQueue
{
    use Queueable;

    private const int BATCH = 200;

    /**
     * @param  list<string>  $memberTypes  the member type values added to or removed from the setting
     */
    public function __construct(
        public readonly array $memberTypes,
    ) {}

    public function handle(AnnouncesMemberYears $years): void
    {
        if ($this->memberTypes === []) {
            return;
        }

        $today = ActivityDays::today();

        MemberModel::query()
            ->whereNotNull('user_id')
            ->where('status', MemberStatus::Active->value)
            ->whereIn('member_type', $this->memberTypes)
            ->chunkById(self::BATCH, function ($members) use ($years, $today): void {
                foreach ($members as $member) {
                    $years->years($member->user_id, MemberYears::permanent(ActivityDays::of($member->joined_at), $today));
                }
            });
    }
}
