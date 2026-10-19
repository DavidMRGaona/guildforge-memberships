<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;
use Modules\Memberships\Listeners\Concerns\NotifiesMember;
use Modules\Memberships\Notifications\MembershipExpiredNotification;

/**
 * Tells the member their membership has expired. Off until send_expired_email is enabled.
 */
final readonly class NotifyMembershipExpired
{
    use NotifiesMember;

    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private MembershipSettingsReader $settings,
    ) {
    }

    public function handle(MembershipExpired $event): void
    {
        if (! $this->settings->expiredEmailEnabled()) {
            return;
        }

        $member = $this->memberRepository->find(new MemberId($event->memberId));

        if ($member === null || in_array($member->memberType, $this->settings->permanentMemberTypes(), true)) {
            return;
        }

        $this->notifyMember($member, new MembershipExpiredNotification(
            memberName: $member->fullName(),
            endDate: $event->endDate,
        ));
    }
}
