<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Repositories;

use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Exceptions\MemberNotFoundException;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;

interface MemberRepositoryInterface
{
    /**
     * Save a member (create or update).
     */
    public function save(Member $member): void;

    /**
     * Find a member by ID.
     */
    public function find(MemberId $id): ?Member;

    /**
     * Find a member by ID or throw an exception.
     *
     * @throws MemberNotFoundException
     */
    public function findOrFail(MemberId $id): Member;

    /**
     * Find a member by member number.
     */
    public function findByMemberNumber(MemberNumber $memberNumber): ?Member;

    /**
     * Find a member by email.
     */
    public function findByEmail(string $email): ?Member;

    /**
     * Find a member by user ID.
     */
    public function findByUserId(string $userId): ?Member;

    /**
     * Delete a member by ID.
     */
    public function delete(MemberId $id): void;

    /**
     * Get all active members.
     *
     * @return array<Member>
     */
    public function getActiveMembers(): array;

    /**
     * Get members by status.
     *
     * @return array<Member>
     */
    public function getByStatus(MemberStatus $status): array;

    /**
     * Search members by first name, last name, or email.
     *
     * @return array<Member>
     */
    public function searchMembers(string $query): array;

    /**
     * Check if a member exists by email.
     */
    public function existsByEmail(string $email): bool;

    /**
     * Check if a member exists by member number.
     */
    public function existsByMemberNumber(MemberNumber $memberNumber): bool;

    /**
     * Get the next available member number for a given year.
     */
    public function getNextMemberNumber(int $year): MemberNumber;

    /**
     * Get all members.
     *
     * @return array<Member>
     */
    public function all(): array;
}
