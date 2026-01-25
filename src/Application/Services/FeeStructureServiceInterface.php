<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\Services;

use Modules\Memberships\Application\DTOs\FeeStructureDTO;

interface FeeStructureServiceInterface
{
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
    ): FeeStructureDTO;

    public function update(
        string $id,
        ?int $amountCents = null,
        ?string $validUntil = null,
        ?string $description = null,
        ?bool $isDefault = null,
        ?array $prorationRules = null
    ): FeeStructureDTO;

    public function find(string $id): ?FeeStructureDTO;

    public function findOrFail(string $id): FeeStructureDTO;

    public function getCurrentStructure(string $memberType, string $periodType): ?FeeStructureDTO;

    public function getDefaultStructure(string $memberType, string $periodType): ?FeeStructureDTO;

    public function delete(string $id): void;

    /**
     * @return array<FeeStructureDTO>
     */
    public function all(): array;
}
