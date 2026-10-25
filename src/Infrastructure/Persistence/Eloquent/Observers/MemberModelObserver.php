<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers;

use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Services\MemberRoleSynchronizer;

/**
 * Keeps the association member role in line when a member is created, changes status,
 * type or linked user, or is deleted (soft or for good) or restored, whatever saved it
 * (the panel or the module services, through the repository).
 *
 * Mass updates (MemberModel::query()->update()) fire no model events; the module does
 * not use them on members.
 */
final readonly class MemberModelObserver
{
    private const array ROLE_FIELDS = ['status', 'member_type', 'user_id'];

    public function __construct(
        private MemberRoleSynchronizer $roles,
    ) {}

    public function created(MemberModel $member): void
    {
        $this->syncLinkedUser($member);
    }

    public function updated(MemberModel $member): void
    {
        if (! $member->wasChanged(self::ROLE_FIELDS)) {
            return;
        }

        if ($member->wasChanged('user_id')) {
            // The user linked before this save, as loaded from the database
            $previousUserId = $member->getOriginal('user_id');

            if (is_string($previousUserId) && $previousUserId !== '') {
                $this->roles->syncUser($previousUserId);
            }
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
