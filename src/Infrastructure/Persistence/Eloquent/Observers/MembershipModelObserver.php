<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Events\MembershipActivated;
use Modules\Memberships\Domain\Events\MembershipDeactivated;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Infrastructure\Activity\ActivityDays;
use Modules\Memberships\Infrastructure\Activity\AnnouncesMemberYears;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
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
        private AnnouncesMemberYears $years,
    ) {}

    public function created(MembershipModel $membership): void
    {
        $this->roles->syncMember($membership->member_id);
        $this->announceCurrentYears($membership);

        if ($membership->status === MembershipStatus::Active) {
            $this->dispatchActivated($membership);
        }
    }

    public function updated(MembershipModel $membership): void
    {
        $previousMemberId = (string) $membership->getRawOriginal('member_id');

        $this->syncMembers($previousMemberId, $membership->member_id);
        $this->dispatchStatusEvents($membership, $previousMemberId);
        $this->announceChangedYears($membership, $previousMemberId);
    }

    public function deleted(MembershipModel $membership): void
    {
        $previousMemberId = (string) $membership->getRawOriginal('member_id');

        $this->syncMembers($previousMemberId, $membership->member_id);
        $this->announceCurrentYears($membership);

        if ($membership->status === MembershipStatus::Active) {
            $this->dispatchDeactivated($membership, $membership->member_id);
        }
    }

    /**
     * Years as a member (core#774): what the period covered before and covers now, for the
     * account of the record it belonged to and of the one it belongs to.
     */
    private function announceChangedYears(MembershipModel $membership, string $previousMemberId): void
    {
        if (! $membership->wasChanged(['member_id', 'status', 'start_date', 'end_date', 'expired_at'])) {
            return;
        }

        $this->years->periodChanged(
            previousMemberId: $previousMemberId !== '' ? $previousMemberId : $membership->member_id,
            previousStatus: (string) $membership->getRawOriginal('status'),
            previousStart: (string) $membership->getRawOriginal('start_date'),
            previousEnd: (string) $membership->getRawOriginal('end_date'),
            previousExpiredOn: $this->day($membership->getOriginal('expired_at')),
            memberId: $membership->member_id,
            status: $membership->status->value,
            start: $membership->start_date->toDateString(),
            end: $membership->end_date->toDateString(),
            expiredOn: ActivityDays::of($membership->expired_at),
        );
    }

    private function announceCurrentYears(MembershipModel $membership): void
    {
        $this->years->period(
            $membership->member_id,
            $membership->status->value,
            $membership->start_date->toDateString(),
            $membership->end_date->toDateString(),
            ActivityDays::of($membership->expired_at),
        );
    }

    private function day(mixed $instant): ?string
    {
        return $instant instanceof DateTimeInterface ? ActivityDays::of($instant) : null;
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
            activatedAt: $membership->activated_at?->toDateTimeImmutable() ?? new DateTimeImmutable,
            occurredAt: new DateTimeImmutable,
        ));
    }

    private function dispatchDeactivated(MembershipModel $membership, string $memberId): void
    {
        $this->events->dispatch(new MembershipDeactivated(
            membershipId: $membership->id,
            memberId: $memberId,
            occurredAt: new DateTimeImmutable,
        ));
    }

    private function dispatchExpired(MembershipModel $membership): void
    {
        $this->events->dispatch(new MembershipExpired(
            membershipId: $membership->id,
            memberId: $membership->member_id,
            endDate: CalendarDate::parse($membership->end_date->toDateString()),
            occurredAt: new DateTimeImmutable,
        ));
    }
}
