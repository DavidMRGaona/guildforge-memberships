<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use Modules\Memberships\Application\DTOs\MemberSituationDTO;
use Modules\Memberships\Application\DTOs\Situation\SituationFeeDTO;
use Modules\Memberships\Application\DTOs\Situation\SituationMembershipDTO;
use Modules\Memberships\Application\DTOs\Situation\SituationPaymentDTO;
use Modules\Memberships\Application\Services\MemberSituationQueryInterface;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Entities\Membership;
use Modules\Memberships\Domain\Entities\MembershipFee;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipFeeRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;

final readonly class MemberSituationQuery implements MemberSituationQueryInterface
{
    public function __construct(
        private MemberRepositoryInterface $members,
        private MembershipRepositoryInterface $memberships,
        private MembershipFeeRepositoryInterface $fees,
        private MembershipSettingsReader $settings,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function forUser(string $userId): ?MemberSituationDTO
    {
        $member = $this->members->findByUserId($userId);

        if (! $member instanceof Member) {
            return null;
        }

        $memberships = $this->memberships->findByMemberId($member->id);
        $now = new DateTimeImmutable;

        $pending = [];
        $lastPaid = null;

        foreach ($memberships as $membership) {
            foreach ($this->fees->findByMembershipId($membership->id) as $fee) {
                if ($fee->isPaid()) {
                    if (! $lastPaid instanceof MembershipFee || $fee->paidAt > $lastPaid->paidAt) {
                        $lastPaid = $fee;
                    }

                    continue;
                }

                // A cancelled membership's fee is never asked for again (nor chased by mail)
                if ($membership->status !== MembershipStatus::Cancelled) {
                    $pending[] = $fee;
                }
            }
        }

        usort($pending, static fn (MembershipFee $a, MembershipFee $b): int => $a->dueDate->format('Y-m-d') <=> $b->dueDate->format('Y-m-d'));

        return new MemberSituationDTO(
            memberNumber: $member->memberNumber->value,
            memberType: $member->memberType->value,
            status: $member->status->value,
            joinedOn: $this->timezone->toDisplay($member->joinedAt)->format('Y-m-d'),
            permanent: in_array($member->memberType, $this->settings->permanentMemberTypes(), true),
            membership: $this->currentMembership($memberships),
            pendingFees: array_map(static fn (MembershipFee $fee): SituationFeeDTO => new SituationFeeDTO(
                amountCents: $fee->amount->amount,
                currency: $fee->amount->currency,
                dueOn: $fee->dueDate->format('Y-m-d'),
                overdue: $fee->isOverdue($now),
            ), $pending),
            lastPayment: $lastPaid instanceof MembershipFee && $lastPaid->paidAt instanceof DateTimeImmutable
                ? new SituationPaymentDTO(
                    amountCents: $lastPaid->amount->amount,
                    currency: $lastPaid->amount->currency,
                    paidOn: $this->timezone->toDisplay($lastPaid->paidAt)->format('Y-m-d'),
                )
                : null,
        );
    }

    /**
     * The membership in force or, without one, the one that starts last (a renewal waiting to
     * start, or the last one that expired).
     *
     * @param  array<Membership>  $memberships
     */
    private function currentMembership(array $memberships): ?SituationMembershipDTO
    {
        $current = null;

        foreach ($memberships as $membership) {
            if ($membership->status === MembershipStatus::Active) {
                $current = $membership;

                break;
            }

            if (! $current instanceof Membership || $membership->startDate > $current->startDate) {
                $current = $membership;
            }
        }

        if (! $current instanceof Membership) {
            return null;
        }

        return new SituationMembershipDTO(
            status: $current->status->value,
            periodType: $current->periodType->value,
            startsOn: $current->startDate->format('Y-m-d'),
            endsOn: $current->endDate->format('Y-m-d'),
        );
    }
}
