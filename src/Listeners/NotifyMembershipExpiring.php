<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Events\MembershipExpiring;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;
use Modules\Memberships\Listeners\Concerns\NotifiesMember;
use Modules\Memberships\Notifications\MembershipExpiringNotification;

final readonly class NotifyMembershipExpiring
{
    use NotifiesMember;

    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private MembershipSettingsReader $settings,
    ) {}

    /**
     * Handle the MembershipExpiring event.
     */
    public function handle(MembershipExpiring $event): void
    {
        $member = $this->memberRepository->find(new MemberId($event->memberId));

        // Permanent members (honorary by default) are never warned about expiry
        if (! $member instanceof Member || in_array($member->memberType, $this->settings->permanentMemberTypes(), true)) {
            return;
        }

        $this->notifyMember($member, new MembershipExpiringNotification(
            memberName: $member->fullName(),
            endDate: $event->endDate,
            daysUntilExpiration: $event->daysUntilExpiration,
        ));
    }
}
