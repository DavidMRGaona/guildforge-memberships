<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use DateTimeImmutable;
use Modules\Memberships\Application\DTOs\CreateMembershipDTO;
use Modules\Memberships\Application\DTOs\MembershipDTO;
use Modules\Memberships\Application\Services\MembershipServiceInterface;
use Modules\Memberships\Domain\Entities\Membership;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;

final readonly class MembershipService implements MembershipServiceInterface
{
    public function __construct(
        private MembershipRepositoryInterface $membershipRepository,
    ) {}

    public function create(CreateMembershipDTO $dto): MembershipDTO
    {
        $periodType = MembershipPeriodType::from($dto->periodType);
        $memberId = MemberId::fromString($dto->memberId);

        // Calculate period dates if not provided
        $startDate = $dto->startDate !== null
            ? new DateTimeImmutable($dto->startDate)
            : new DateTimeImmutable();

        $periodDates = $periodType->getPeriodDates($startDate);

        $endDate = $dto->endDate !== null
            ? new DateTimeImmutable($dto->endDate)
            : $periodDates['end'];

        $membership = new Membership(
            id: MembershipId::generate(),
            memberId: $memberId,
            periodType: $periodType,
            startDate: $periodDates['start'],
            endDate: $endDate,
            status: MembershipStatus::Pending,
            activatedAt: null,
            cancelledAt: null,
            notes: $dto->notes,
        );

        $this->membershipRepository->save($membership);

        return MembershipDTO::fromEntity($membership);
    }

    public function activate(string $id): MembershipDTO
    {
        $membership = $this->membershipRepository->findOrFail(MembershipId::fromString($id));
        $membership->activate();
        $this->membershipRepository->save($membership);

        return MembershipDTO::fromEntity($membership);
    }

    public function expire(string $id): MembershipDTO
    {
        $membership = $this->membershipRepository->findOrFail(MembershipId::fromString($id));
        $membership->expire();
        $this->membershipRepository->save($membership);

        return MembershipDTO::fromEntity($membership);
    }

    public function cancel(string $id): MembershipDTO
    {
        $membership = $this->membershipRepository->findOrFail(MembershipId::fromString($id));
        $membership->cancel();
        $this->membershipRepository->save($membership);

        return MembershipDTO::fromEntity($membership);
    }

    public function find(string $id): ?MembershipDTO
    {
        $membership = $this->membershipRepository->find(MembershipId::fromString($id));

        return $membership !== null ? MembershipDTO::fromEntity($membership) : null;
    }

    public function findOrFail(string $id): MembershipDTO
    {
        $membership = $this->membershipRepository->findOrFail(MembershipId::fromString($id));

        return MembershipDTO::fromEntity($membership);
    }

    /**
     * @return array<MembershipDTO>
     */
    public function findByMemberId(string $memberId): array
    {
        $memberships = $this->membershipRepository->findByMemberId(MemberId::fromString($memberId));

        return array_map(
            fn (Membership $membership) => MembershipDTO::fromEntity($membership),
            $memberships
        );
    }

    public function findActiveMembership(string $memberId): ?MembershipDTO
    {
        $membership = $this->membershipRepository->findActiveMembership(MemberId::fromString($memberId));

        return $membership !== null ? MembershipDTO::fromEntity($membership) : null;
    }

    /**
     * @return array<MembershipDTO>
     */
    public function getExpiringMemberships(int $daysFromNow = 30): array
    {
        $memberships = $this->membershipRepository->getExpiringMemberships($daysFromNow);

        return array_map(
            fn (Membership $membership) => MembershipDTO::fromEntity($membership),
            $memberships
        );
    }

    public function delete(string $id): void
    {
        $this->membershipRepository->delete(MembershipId::fromString($id));
    }
}
