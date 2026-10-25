<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
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
        private MembershipRepositoryInterface $membershipRepository,
        private MembershipSettingsReader $settings,
    ) {}

    public function handle(MembershipExpired $event): void
    {
        if (! $this->settings->expiredEmailEnabled()) {
            return;
        }

        $member = $this->memberRepository->find(new MemberId($event->memberId));

        if (! $member instanceof Member || ! $member->isActive() || in_array($member->memberType, $this->settings->permanentMemberTypes(), true)) {
            return;
        }

        // Nothing to renew when the next membership is already there
        if ($this->hasCurrentOrUpcomingMembership($member->id, $event->membershipId)) {
            return;
        }

        $this->notifyMember($member, new MembershipExpiredNotification(
            memberName: $member->fullName(),
            endDate: $event->endDate,
        ));
    }

    private function hasCurrentOrUpcomingMembership(MemberId $memberId, string $expiredMembershipId): bool
    {
        $today = new DateTimeImmutable('today');

        return array_any($this->membershipRepository->findByMemberId($memberId), fn ($membership) => $membership->id->value !== $expiredMembershipId
            && $membership->status !== MembershipStatus::Cancelled
            && $membership->status !== MembershipStatus::Expired
            && $membership->endDate >= $today);
    }
}
