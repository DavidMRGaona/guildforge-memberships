<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Repositories;

use Modules\Memberships\Domain\Entities\MembershipFee;
use Modules\Memberships\Domain\Exceptions\MembershipFeeNotFoundException;
use Modules\Memberships\Domain\ValueObjects\MembershipFeeId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;

interface MembershipFeeRepositoryInterface
{
    /**
     * Save a membership fee (create or update).
     */
    public function save(MembershipFee $fee): void;

    /**
     * Find a membership fee by ID.
     */
    public function find(MembershipFeeId $id): ?MembershipFee;

    /**
     * Find a membership fee by ID or throw an exception.
     *
     * @throws MembershipFeeNotFoundException
     */
    public function findOrFail(MembershipFeeId $id): MembershipFee;

    /**
     * Find all fees for a membership.
     *
     * @return array<MembershipFee>
     */
    public function findByMembershipId(MembershipId $membershipId): array;

    /**
     * Get all overdue fees (unpaid and dueDate < today).
     *
     * @return array<MembershipFee>
     */
    public function getOverdueFees(): array;

    /**
     * Get all unpaid fees.
     *
     * @return array<MembershipFee>
     */
    public function getUnpaidFees(): array;

    /**
     * Delete a membership fee by ID.
     */
    public function delete(MembershipFeeId $id): void;

    /**
     * Get all membership fees.
     *
     * @return array<MembershipFee>
     */
    public function all(): array;

    /**
     * Count unpaid fees.
     */
    public function countUnpaid(): int;
}
