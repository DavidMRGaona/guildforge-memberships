<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners\Concerns;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Modules\Memberships\Domain\Entities\Member;

trait NotifiesMember
{
    /**
     * Send to the linked account when there is one, otherwise to the member's own email.
     */
    private function notifyMember(Member $member, Notification $notification): void
    {
        if ($member->isLinkedToUser()) {
            $user = UserModel::find($member->userId);

            if ($user !== null) {
                $user->notify($notification);

                return;
            }
        }

        if ($member->email !== null && $member->email !== '') {
            NotificationFacade::route('mail', $member->email)->notify($notification);
        }
    }
}
