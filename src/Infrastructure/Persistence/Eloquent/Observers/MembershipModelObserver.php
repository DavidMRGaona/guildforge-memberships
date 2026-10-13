<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Events\MembershipActivated;
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
        if (! $membership->wasChanged('status')) {
            return;
        }

        match ($membership->status) {
            MembershipStatus::Active => $this->dispatchActivated($membership),
            MembershipStatus::Expired => $this->dispatchExpired($membership),
            default => null,
        };
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
