<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use DateTimeImmutable;
use Modules\Memberships\Application\DTOs\CreateMemberDTO;
use Modules\Memberships\Application\DTOs\MemberDTO;
use Modules\Memberships\Application\DTOs\UpdateMemberDTO;
use Modules\Memberships\Application\Services\MemberNumberGeneratorInterface;
use Modules\Memberships\Application\Services\MemberServiceInterface;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;

final readonly class MemberService implements MemberServiceInterface
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private MembershipRepositoryInterface $membershipRepository,
        private MemberNumberGeneratorInterface $memberNumberGenerator,
    ) {}

    public function create(CreateMemberDTO $dto): MemberDTO
    {
        $memberNumber = $this->memberNumberGenerator->generate();

        $member = new Member(
            id: MemberId::generate(),
            memberNumber: $memberNumber,
            firstName: $dto->firstName,
            lastName: $dto->lastName,
            email: $dto->email,
            phone: $dto->phone,
            birthDate: $dto->birthDate !== null ? new DateTimeImmutable($dto->birthDate) : null,
            address: $dto->address,
            memberType: MemberType::from($dto->memberType),
            status: MemberStatus::Active,
            userId: $dto->userId,
            notes: $dto->notes,
            joinedAt: $dto->joinedAt !== null ? new DateTimeImmutable($dto->joinedAt) : new DateTimeImmutable(),
        );

        $this->memberRepository->save($member);

        return MemberDTO::fromEntity($member);
    }

    public function update(UpdateMemberDTO $dto): MemberDTO
    {
        $member = $this->memberRepository->findOrFail(MemberId::fromString($dto->id));

        if ($dto->firstName !== null || $dto->lastName !== null || $dto->birthDate !== null) {
            $member->updatePersonalInfo(
                firstName: $dto->firstName ?? $member->firstName(),
                lastName: $dto->lastName ?? $member->lastName(),
                birthDate: $dto->birthDate !== null ? new DateTimeImmutable($dto->birthDate) : $member->birthDate(),
            );
        }

        if ($dto->email !== null || $dto->phone !== null || $dto->address !== null) {
            $member->updateContactInfo(
                email: $dto->email ?? $member->email(),
                phone: $dto->phone ?? $member->phone(),
                address: $dto->address ?? $member->address(),
            );
        }

        if ($dto->status !== null) {
            $member->changeStatus(MemberStatus::from($dto->status));
        }

        if ($dto->userId !== null) {
            $member->linkToUser($dto->userId);
        }

        $this->memberRepository->save($member);

        $activeMembership = $this->membershipRepository->findActiveMembership($member->id);

        return MemberDTO::fromEntity($member, $activeMembership);
    }

    public function delete(string $id): void
    {
        $this->memberRepository->delete(MemberId::fromString($id));
    }

    public function find(string $id): ?MemberDTO
    {
        $member = $this->memberRepository->find(MemberId::fromString($id));

        if ($member === null) {
            return null;
        }

        $activeMembership = $this->membershipRepository->findActiveMembership($member->id);

        return MemberDTO::fromEntity($member, $activeMembership);
    }

    public function findOrFail(string $id): MemberDTO
    {
        $member = $this->memberRepository->findOrFail(MemberId::fromString($id));
        $activeMembership = $this->membershipRepository->findActiveMembership($member->id);

        return MemberDTO::fromEntity($member, $activeMembership);
    }

    public function findByMemberNumber(string $memberNumber): ?MemberDTO
    {
        $member = $this->memberRepository->findByMemberNumber(
            \Modules\Memberships\Domain\ValueObjects\MemberNumber::fromString($memberNumber)
        );

        if ($member === null) {
            return null;
        }

        $activeMembership = $this->membershipRepository->findActiveMembership($member->id);

        return MemberDTO::fromEntity($member, $activeMembership);
    }

    public function findByEmail(string $email): ?MemberDTO
    {
        $member = $this->memberRepository->findByEmail($email);

        if ($member === null) {
            return null;
        }

        $activeMembership = $this->membershipRepository->findActiveMembership($member->id);

        return MemberDTO::fromEntity($member, $activeMembership);
    }

    /**
     * @return array<MemberDTO>
     */
    public function getActiveMembers(): array
    {
        $members = $this->memberRepository->getActiveMembers();

        return array_map(
            fn (Member $member) => MemberDTO::fromEntity(
                $member,
                $this->membershipRepository->findActiveMembership($member->id)
            ),
            $members
        );
    }

    /**
     * @return array<MemberDTO>
     */
    public function searchMembers(string $query): array
    {
        $members = $this->memberRepository->searchMembers($query);

        return array_map(
            fn (Member $member) => MemberDTO::fromEntity(
                $member,
                $this->membershipRepository->findActiveMembership($member->id)
            ),
            $members
        );
    }

    public function changeStatus(string $id, string $status): MemberDTO
    {
        $member = $this->memberRepository->findOrFail(MemberId::fromString($id));
        $member->changeStatus(MemberStatus::from($status));
        $this->memberRepository->save($member);

        $activeMembership = $this->membershipRepository->findActiveMembership($member->id);

        return MemberDTO::fromEntity($member, $activeMembership);
    }

    public function linkToUser(string $memberId, string $userId): MemberDTO
    {
        $member = $this->memberRepository->findOrFail(MemberId::fromString($memberId));
        $member->linkToUser($userId);
        $this->memberRepository->save($member);

        $activeMembership = $this->membershipRepository->findActiveMembership($member->id);

        return MemberDTO::fromEntity($member, $activeMembership);
    }

    public function unlinkFromUser(string $memberId): MemberDTO
    {
        $member = $this->memberRepository->findOrFail(MemberId::fromString($memberId));
        $member->unlinkFromUser();
        $this->memberRepository->save($member);

        $activeMembership = $this->membershipRepository->findActiveMembership($member->id);

        return MemberDTO::fromEntity($member, $activeMembership);
    }

    /**
     * @return array<MemberDTO>
     */
    public function all(): array
    {
        $members = $this->memberRepository->all();

        return array_map(
            fn (Member $member) => MemberDTO::fromEntity(
                $member,
                $this->membershipRepository->findActiveMembership($member->id)
            ),
            $members
        );
    }
}
