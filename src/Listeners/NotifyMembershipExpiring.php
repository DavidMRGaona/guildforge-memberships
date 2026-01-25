<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Modules\Memberships\Domain\Events\MembershipExpiring;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Notifications\MembershipExpiringNotification;

final readonly class NotifyMembershipExpiring
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
    ) {
    }

    /**
     * Handle the MembershipExpiring event.
     */
    public function handle(MembershipExpiring $event): void
    {
        // Get the member
        $member = $this->memberRepository->find(new MemberId($event->memberId));

        if ($member === null) {
            return;
        }

        // Get the email to notify
        $email = $this->getNotificationEmail($member);

        if ($email === null) {
            return;
        }

        // Create the notification
        $notification = new MembershipExpiringNotification(
            memberName: $member->fullName(),
            endDate: $event->endDate,
            daysUntilExpiration: $event->daysUntilExpiration,
        );

        // If member is linked to a user, notify the user directly
        if ($member->isLinkedToUser()) {
            $user = UserModel::find($member->userId);
            if ($user !== null) {
                $user->notify($notification);

                return;
            }
        }

        // Otherwise, send to the member's email as an anonymous notification
        Notification::route('mail', $email)->notify($notification);
    }

    /**
     * Get the email address to send notification to.
     */
    private function getNotificationEmail(\Modules\Memberships\Domain\Entities\Member $member): ?string
    {
        // First, try to get email from linked user
        if ($member->isLinkedToUser()) {
            $user = UserModel::find($member->userId);
            if ($user !== null) {
                return $user->email;
            }
        }

        // Fall back to member's email
        return $member->email;
    }
}
