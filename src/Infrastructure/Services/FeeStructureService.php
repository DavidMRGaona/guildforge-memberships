<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use DateTimeImmutable;
use Modules\Memberships\Application\DTOs\FeeStructureDTO;
use Modules\Memberships\Application\Services\FeeStructureServiceInterface;
use Modules\Memberships\Domain\Entities\FeeStructure;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Repositories\FeeStructureRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\FeeStructureId;
use Modules\Memberships\Domain\ValueObjects\Money;

final readonly class FeeStructureService implements FeeStructureServiceInterface
{
    public function __construct(
        private FeeStructureRepositoryInterface $feeStructureRepository,
    ) {}

    public function create(
        string $memberType,
        string $periodType,
        int $amountCents,
        string $currency,
        string $validFrom,
        ?string $validUntil = null,
        ?string $description = null,
        bool $isDefault = false,
        ?array $prorationRules = null
    ): FeeStructureDTO {
        $feeStructure = new FeeStructure(
            id: FeeStructureId::generate(),
            memberType: MemberType::from($memberType),
            periodType: MembershipPeriodType::from($periodType),
            amount: Money::fromCents($amountCents, $currency),
            prorationRules: $prorationRules,
            validFrom: new DateTimeImmutable($validFrom),
            validUntil: $validUntil !== null ? new DateTimeImmutable($validUntil) : null,
            description: $description,
            isDefault: $isDefault,
        );

        $this->feeStructureRepository->save($feeStructure);

        return FeeStructureDTO::fromEntity($feeStructure);
    }

    public function update(
        string $id,
        ?int $amountCents = null,
        ?string $validUntil = null,
        ?string $description = null,
        ?bool $isDefault = null,
        ?array $prorationRules = null
    ): FeeStructureDTO {
        $feeStructure = $this->feeStructureRepository->findOrFail(FeeStructureId::fromString($id));

        if ($amountCents !== null) {
            $feeStructure->updateAmount(Money::fromCents($amountCents, $feeStructure->amount()->currency()));
        }

        if ($validUntil !== null) {
            $feeStructure->setValidUntil(new DateTimeImmutable($validUntil));
        }

        if ($isDefault === true) {
            $feeStructure->setAsDefault();
        } elseif ($isDefault === false) {
            $feeStructure->unsetAsDefault();
        }

        $this->feeStructureRepository->save($feeStructure);

        return FeeStructureDTO::fromEntity($feeStructure);
    }

    public function find(string $id): ?FeeStructureDTO
    {
        $feeStructure = $this->feeStructureRepository->find(FeeStructureId::fromString($id));

        return $feeStructure !== null ? FeeStructureDTO::fromEntity($feeStructure) : null;
    }

    public function findOrFail(string $id): FeeStructureDTO
    {
        $feeStructure = $this->feeStructureRepository->findOrFail(FeeStructureId::fromString($id));

        return FeeStructureDTO::fromEntity($feeStructure);
    }

    public function getCurrentStructure(string $memberType, string $periodType): ?FeeStructureDTO
    {
        $feeStructure = $this->feeStructureRepository->getCurrentStructure(
            MemberType::from($memberType),
            MembershipPeriodType::from($periodType)
        );

        return $feeStructure !== null ? FeeStructureDTO::fromEntity($feeStructure) : null;
    }

    public function getDefaultStructure(string $memberType, string $periodType): ?FeeStructureDTO
    {
        $feeStructure = $this->feeStructureRepository->getDefaultStructure(
            MemberType::from($memberType),
            MembershipPeriodType::from($periodType)
        );

        return $feeStructure !== null ? FeeStructureDTO::fromEntity($feeStructure) : null;
    }

    public function delete(string $id): void
    {
        $this->feeStructureRepository->delete(FeeStructureId::fromString($id));
    }

    /**
     * @return array<FeeStructureDTO>
     */
    public function all(): array
    {
        $feeStructures = $this->feeStructureRepository->all();

        return array_map(
            fn (FeeStructure $feeStructure) => FeeStructureDTO::fromEntity($feeStructure),
            $feeStructures
        );
    }
}
