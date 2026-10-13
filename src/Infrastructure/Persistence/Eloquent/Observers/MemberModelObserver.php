<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers;

use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Services\MemberRoleSynchronizer;

/**
 * Keeps the member role in line when a member is linked to another user,
 * unlinked, deleted (soft or for good) or restored.
 */
final readonly class MemberModelObserver
{
    public function __construct(
        private MemberRoleSynchronizer $roles,
    ) {
    }

    public function updated(MemberModel $member): void
    {
        if (! $member->wasChanged('user_id')) {
            return;
        }

        $previousUserId = $member->getRawOriginal('user_id');

        if (is_string($previousUserId)) {
            $this->roles->syncUser($previousUserId);
        }

        $this->syncLinkedUser($member);
    }

    public function deleted(MemberModel $member): void
    {
        $this->syncLinkedUser($member);
    }

    public function restored(MemberModel $member): void
    {
        $this->syncLinkedUser($member);
    }

    private function syncLinkedUser(MemberModel $member): void
    {
        if ($member->user_id !== null) {
            $this->roles->syncUser($member->user_id);
        }
    }
}
