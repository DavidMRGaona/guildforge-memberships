<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\Services;

use Modules\Memberships\Application\DTOs\CreateMemberDTO;
use Modules\Memberships\Application\DTOs\MemberDTO;
use Modules\Memberships\Application\DTOs\UpdateMemberDTO;

interface MemberServiceInterface
{
    public function create(CreateMemberDTO $dto): MemberDTO;

    public function update(UpdateMemberDTO $dto): MemberDTO;

    public function delete(string $id): void;

    public function find(string $id): ?MemberDTO;

    public function findOrFail(string $id): MemberDTO;

    public function findByMemberNumber(string $memberNumber): ?MemberDTO;

    public function findByEmail(string $email): ?MemberDTO;

    /**
     * @return array<MemberDTO>
     */
    public function getActiveMembers(): array;

    /**
     * @return array<MemberDTO>
     */
    public function searchMembers(string $query): array;

    public function changeStatus(string $id, string $status): MemberDTO;

    public function linkToUser(string $memberId, string $userId): MemberDTO;

    public function unlinkFromUser(string $memberId): MemberDTO;

    /**
     * @return array<MemberDTO>
     */
    public function all(): array;
}
