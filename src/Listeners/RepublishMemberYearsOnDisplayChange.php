<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use App\Domain\Profiles\Events\ProfileMembershipDisplayChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Memberships\Infrastructure\Activity\AnnouncesMemberYears;

/**
 * Years as a member (core#774) are reported only for accounts that show their membership: when
 * that may have changed (the badge, the profile's visibility or public name, the association role,
 * the account's state) every year of the account is published again, and the core reads what is
 * true now, which may be nothing.
 */
final class RepublishMemberYearsOnDisplayChange implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(
        private readonly AnnouncesMemberYears $years,
    ) {}

    public function handle(ProfileMembershipDisplayChanged $event): void
    {
        $this->years->years($event->userId, $this->years->yearsOfAccount($event->userId));
    }
}
