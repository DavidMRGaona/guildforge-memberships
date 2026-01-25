<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\Services;

use Modules\Memberships\Application\DTOs\MembershipFeeDTO;
use Modules\Memberships\Application\DTOs\RecordPaymentDTO;

interface MembershipFeeServiceInterface
{
    public function createFee(
        string $membershipId,
        int $amountCents,
        string $currency,
        string $dueDate,
        ?string $notes = null
    ): MembershipFeeDTO;

    public function recordPayment(RecordPaymentDTO $dto): MembershipFeeDTO;

    public function find(string $id): ?MembershipFeeDTO;

    public function findOrFail(string $id): MembershipFeeDTO;

    /**
     * @return array<MembershipFeeDTO>
     */
    public function findByMembershipId(string $membershipId): array;

    /**
     * @return array<MembershipFeeDTO>
     */
    public function getOverdueFees(): array;

    /**
     * @return array<MembershipFeeDTO>
     */
    public function getUnpaidFees(): array;

    public function delete(string $id): void;
}
