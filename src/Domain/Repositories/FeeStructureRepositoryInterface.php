<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Repositories;

use Modules\Memberships\Domain\Entities\FeeStructure;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Exceptions\FeeStructureNotFoundException;
use Modules\Memberships\Domain\ValueObjects\FeeStructureId;

interface FeeStructureRepositoryInterface
{
    /**
     * Save a fee structure (create or update).
     */
    public function save(FeeStructure $feeStructure): void;

    /**
     * Find a fee structure by ID.
     */
    public function find(FeeStructureId $id): ?FeeStructure;

    /**
     * Find a fee structure by ID or throw an exception.
     *
     * @throws FeeStructureNotFoundException
     */
    public function findOrFail(FeeStructureId $id): FeeStructure;

    /**
     * Find all fee structures for a member type.
     *
     * @return array<FeeStructure>
     */
    public function findByMemberType(MemberType $memberType): array;

    /**
     * Get the currently valid fee structure for a member type and period type.
     */
    public function getCurrentStructure(MemberType $memberType, MembershipPeriodType $periodType): ?FeeStructure;

    /**
     * Get the default fee structure for a member type and period type.
     */
    public function getDefaultStructure(MemberType $memberType, MembershipPeriodType $periodType): ?FeeStructure;

    /**
     * Delete a fee structure by ID.
     */
    public function delete(FeeStructureId $id): void;

    /**
     * Get all fee structures.
     *
     * @return array<FeeStructure>
     */
    public function all(): array;
}
