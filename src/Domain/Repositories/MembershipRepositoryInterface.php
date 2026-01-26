<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Repositories;

use DateTimeImmutable;
use Modules\Memberships\Domain\Entities\Membership;
use Modules\Memberships\Domain\Exceptions\MembershipNotFoundException;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;

interface MembershipRepositoryInterface
{
    /**
     * Save a membership (create or update).
     */
    public function save(Membership $membership): void;

    /**
     * Find a membership by ID.
     */
    public function find(MembershipId $id): ?Membership;

    /**
     * Find a membership by ID or throw an exception.
     *
     * @throws MembershipNotFoundException
     */
    public function findOrFail(MembershipId $id): Membership;

    /**
     * Find all memberships for a member.
     *
     * @return array<Membership>
     */
    public function findByMemberId(MemberId $memberId): array;

    /**
     * Find the active membership for a member.
     */
    public function findActiveMembership(MemberId $memberId): ?Membership;

    /**
     * Get memberships expiring within the given number of days.
     *
     * @return array<Membership>
     */
    public function getExpiringMemberships(int $daysFromNow): array;

    /**
     * Get all expired memberships (status = expired or endDate < today).
     *
     * @return array<Membership>
     */
    public function getExpiredMemberships(): array;

    /**
     * Find active memberships that have expired (end_date < given date but status = Active).
     *
     * @return array<Membership>
     */
    public function findActiveExpiredBefore(DateTimeImmutable $date): array;

    /**
     * Delete a membership by ID.
     */
    public function delete(MembershipId $id): void;

    /**
     * Check if a membership overlaps with existing memberships for a member.
     */
    public function hasOverlappingMembership(
        MemberId $memberId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?MembershipId $excludeId = null,
    ): bool;

    /**
     * Get all memberships.
     *
     * @return array<Membership>
     */
    public function all(): array;
}
