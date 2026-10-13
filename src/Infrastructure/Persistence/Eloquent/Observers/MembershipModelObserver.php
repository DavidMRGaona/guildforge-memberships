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

/**
 * Turns status changes of a membership into domain events, whatever saved it
 * (the panel, MembershipService or the expiry command).
 */
final readonly class MembershipModelObserver
{
    public function __construct(
        private Dispatcher $events,
    ) {
    }

    public function created(MembershipModel $membership): void
    {
        if ($membership->status === MembershipStatus::Active) {
            $this->dispatchActivated($membership);
        }
    }

    public function updated(MembershipModel $membership): void
    {
        $statusChanged = $membership->wasChanged('status');
        $memberChanged = $membership->wasChanged('member_id');

        if (! $statusChanged && ! $memberChanged) {
            return;
        }

        $wasActive = $membership->getRawOriginal('status') === MembershipStatus::Active->value;
        $isActive = $membership->status === MembershipStatus::Active;

        if ($wasActive && (! $isActive || $memberChanged)) {
            $this->dispatchDeactivated($membership, (string) $membership->getRawOriginal('member_id'));
        }

        if ($isActive) {
            $this->dispatchActivated($membership);
        }

        if ($statusChanged && $membership->status === MembershipStatus::Expired) {
            $this->dispatchExpired($membership);
        }
    }

    public function deleted(MembershipModel $membership): void
    {
        if ($membership->status === MembershipStatus::Active) {
            $this->dispatchDeactivated($membership, $membership->member_id);
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
