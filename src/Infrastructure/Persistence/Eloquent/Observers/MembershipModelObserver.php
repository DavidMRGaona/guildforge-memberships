<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Events\MembershipActivated;
use Modules\Memberships\Domain\Events\MembershipDeactivated;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\MemberRoleSynchronizer;

/**
 * Keeps the member role in line with the memberships and turns status changes into
 * domain events, whatever saved the membership (the panel, MembershipService or the
 * expiry command).
 *
 * The role is re-evaluated from the database after every write, for the previous and
 * the current member, so a stale in-memory copy cannot leave a role behind. The events
 * describe the change as this copy saw it.
 */
final readonly class MembershipModelObserver
{
    public function __construct(
        private Dispatcher $events,
        private MemberRoleSynchronizer $roles,
    ) {
    }

    public function created(MembershipModel $membership): void
    {
        $this->roles->syncMember($membership->member_id);

        if ($membership->status === MembershipStatus::Active) {
            $this->dispatchActivated($membership);
        }
    }

    public function updated(MembershipModel $membership): void
    {
        $previousMemberId = (string) $membership->getRawOriginal('member_id');

        $this->syncMembers($previousMemberId, $membership->member_id);
        $this->dispatchStatusEvents($membership, $previousMemberId);
    }

    public function deleted(MembershipModel $membership): void
    {
        $previousMemberId = (string) $membership->getRawOriginal('member_id');

        $this->syncMembers($previousMemberId, $membership->member_id);

        if ($membership->status === MembershipStatus::Active) {
            $this->dispatchDeactivated($membership, $membership->member_id);
        }
    }

    private function syncMembers(string $previousMemberId, string $memberId): void
    {
        if ($previousMemberId !== '' && $previousMemberId !== $memberId) {
            $this->roles->syncMember($previousMemberId);
        }

        $this->roles->syncMember($memberId);
    }

    private function dispatchStatusEvents(MembershipModel $membership, string $previousMemberId): void
    {
        $statusChanged = $membership->wasChanged('status');
        $memberChanged = $membership->wasChanged('member_id');

        if (! $statusChanged && ! $memberChanged) {
            return;
        }

        $wasActive = $membership->getRawOriginal('status') === MembershipStatus::Active->value;
        $isActive = $membership->status === MembershipStatus::Active;

        if ($wasActive && (! $isActive || $memberChanged)) {
            $this->dispatchDeactivated($membership, $previousMemberId);
        }

        if ($isActive) {
            $this->dispatchActivated($membership);
        }

        if ($statusChanged && $membership->status === MembershipStatus::Expired) {
            $this->dispatchExpired($membership);
        }
    }

    private function dispatchActivated(MembershipModel $membership): void
    {
        $this->events->dispatch(new MembershipActivated(
            membershipId: $membership->id,
            memberId: $membership->member_id,
            activatedAt: $membership->activated_at?->toDateTimeImmutable() ?? new DateTimeImmutable(),
            occurredAt: new DateTimeImmutable(),
        ));
    }

    private function dispatchDeactivated(MembershipModel $membership, string $memberId): void
    {
        $this->events->dispatch(new MembershipDeactivated(
            membershipId: $membership->id,
            memberId: $memberId,
            occurredAt: new DateTimeImmutable(),
        ));
    }

    private function dispatchExpired(MembershipModel $membership): void
    {
        $this->events->dispatch(new MembershipExpired(
            membershipId: $membership->id,
            memberId: $membership->member_id,
            endDate: new DateTimeImmutable($membership->end_date->toDateString()),
            occurredAt: new DateTimeImmutable(),
        ));
    }
}
