<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\Services;

use Modules\Memberships\Application\DTOs\CreateMembershipDTO;
use Modules\Memberships\Application\DTOs\MembershipDTO;

interface MembershipServiceInterface
{
    public function create(CreateMembershipDTO $dto): MembershipDTO;

    public function activate(string $id): MembershipDTO;

    public function expire(string $id): MembershipDTO;

    public function cancel(string $id): MembershipDTO;

    public function find(string $id): ?MembershipDTO;

    public function findOrFail(string $id): MembershipDTO;

    /**
     * @return array<MembershipDTO>
     */
    public function findByMemberId(string $memberId): array;

    public function findActiveMembership(string $memberId): ?MembershipDTO;

    /**
     * @return array<MembershipDTO>
     */
    public function getExpiringMemberships(int $daysFromNow = 30): array;

    public function delete(string $id): void;
}
