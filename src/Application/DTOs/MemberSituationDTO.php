<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

use Modules\Memberships\Application\DTOs\Situation\SituationFeeDTO;
use Modules\Memberships\Application\DTOs\Situation\SituationMembershipDTO;
use Modules\Memberships\Application\DTOs\Situation\SituationPaymentDTO;

/**
 * What the person sees in the profile's member tab about the record linked to their account.
 * Enum values travel as such (the page translates them) and calendar days as `Y-m-d` of the
 * site's display zone.
 */
final readonly class MemberSituationDTO
{
    /**
     * @param  list<SituationFeeDTO>  $pendingFees  unpaid fees by due day, oldest first
     */
    public function __construct(
        public string $memberNumber,
        public string $memberType,
        public string $status,
        public string $joinedOn,
        /** The member's type keeps the association role without a membership (module settings). */
        public bool $permanent,
        public ?SituationMembershipDTO $membership,
        public array $pendingFees,
        public ?SituationPaymentDTO $lastPayment,
    ) {}

    /** The tab's badge: fees already past their due day. */
    public function overdueCount(): int
    {
        return count(array_filter($this->pendingFees, static fn (SituationFeeDTO $fee): bool => $fee->overdue));
    }

    /**
     * @return array{
     *     memberNumber: string,
     *     memberType: string,
     *     status: string,
     *     joinedOn: string,
     *     permanent: bool,
     *     membership: array{status: string, periodType: string, startsOn: string, endsOn: string}|null,
     *     pendingFees: list<array{amountCents: int, currency: string, dueOn: string, overdue: bool}>,
     *     lastPayment: array{amountCents: int, currency: string, paidOn: string}|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'memberNumber' => $this->memberNumber,
            'memberType' => $this->memberType,
            'status' => $this->status,
            'joinedOn' => $this->joinedOn,
            'permanent' => $this->permanent,
            'membership' => $this->membership?->toArray(),
            'pendingFees' => array_map(static fn (SituationFeeDTO $fee): array => $fee->toArray(), $this->pendingFees),
            'lastPayment' => $this->lastPayment?->toArray(),
        ];
    }
}
