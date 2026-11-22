<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers;

use Modules\Memberships\Infrastructure\Activity\AnnouncesMemberYears;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Services\MemberRoleSynchronizer;
use WeakMap;

/**
 * Keeps the association member role in line when a member is created, changes status,
 * type or linked user, or is deleted (soft or for good) or restored, whatever saved it
 * (the panel or the module services, through the repository).
 *
 * It also tells the core (core#774) which years as a member of which account may have changed
 * when a record is linked to or unlinked from an account, deleted or restored: the status and
 * type of the record do not change the years its periods were in force.
 *
 * Mass updates (MemberModel::query()->update()) fire no model events; the module does
 * not use them on members.
 */
final class MemberModelObserver
{
    private const array ROLE_FIELDS = ['status', 'member_type', 'user_id'];

    /**
     * The years a record's periods covered, taken before it is deleted for good: the database
     * takes the periods with it, so afterwards there is nothing left to read them from. Kept
     * outside the observer, which Laravel builds again for every model event.
     *
     * @var WeakMap<MemberModel, list<int>>|null
     */
    private static ?WeakMap $yearsLostByDeletion = null;

    public function __construct(
        private readonly MemberRoleSynchronizer $roles,
        private readonly AnnouncesMemberYears $years,
    ) {}

    public function created(MemberModel $member): void
    {
        $this->syncLinkedUser($member);
    }

    public function updated(MemberModel $member): void
    {
        if ($member->wasChanged('user_id')) {
            $previous = $member->getOriginal('user_id');

            $this->years->member($member, is_string($previous) ? $previous : null);
            $this->years->member($member, $member->user_id);
        }

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
        if (! $member->isForceDeleting()) {
            $this->years->member($member, $member->user_id);
        }

        $this->syncLinkedUser($member);
    }

    public function forceDeleting(MemberModel $member): void
    {
        if ($member->user_id !== null) {
            $this->lostYears()[$member] = $this->years->yearsOfMember($member->id);
        }
    }

    public function forceDeleted(MemberModel $member): void
    {
        $lost = $this->lostYears();

        $this->years->years($member->user_id, $lost[$member] ?? []);
        unset($lost[$member]);
    }

    public function restored(MemberModel $member): void
    {
        $this->years->member($member, $member->user_id);
        $this->syncLinkedUser($member);
    }

    /**
     * @return WeakMap<MemberModel, list<int>>
     */
    private function lostYears(): WeakMap
    {
        return self::$yearsLostByDeletion ??= new WeakMap;
    }

    private function syncLinkedUser(MemberModel $member): void
    {
        if ($member->user_id !== null) {
            $this->roles->syncUser($member->user_id);
        }
    }
}
