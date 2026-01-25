<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use DateTimeImmutable;
use Modules\Memberships\Application\DTOs\MembershipFeeDTO;
use Modules\Memberships\Application\DTOs\RecordPaymentDTO;
use Modules\Memberships\Application\Services\MembershipFeeServiceInterface;
use Modules\Memberships\Domain\Entities\MembershipFee;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Domain\Repositories\MembershipFeeRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MembershipFeeId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Domain\ValueObjects\Money;

final readonly class MembershipFeeService implements MembershipFeeServiceInterface
{
    public function __construct(
        private MembershipFeeRepositoryInterface $feeRepository,
    ) {}

    public function createFee(
        string $membershipId,
        int $amountCents,
        string $currency,
        string $dueDate,
        ?string $notes = null
    ): MembershipFeeDTO {
        $fee = new MembershipFee(
            id: MembershipFeeId::generate(),
            membershipId: MembershipId::fromString($membershipId),
            amount: Money::fromCents($amountCents, $currency),
            dueDate: new DateTimeImmutable($dueDate),
            paidAt: null,
            paymentMethod: null,
            transactionReference: null,
            notes: $notes,
        );

        $this->feeRepository->save($fee);

        return MembershipFeeDTO::fromEntity($fee);
    }

    public function recordPayment(RecordPaymentDTO $dto): MembershipFeeDTO
    {
        $fee = $this->feeRepository->findOrFail(MembershipFeeId::fromString($dto->feeId));

        $paidAt = $dto->paidAt !== null
            ? new DateTimeImmutable($dto->paidAt)
            : new DateTimeImmutable();

        $paymentMethod = PaymentMethod::from($dto->paymentMethod);

        $fee->recordPayment($paidAt, $paymentMethod, $dto->transactionReference);

        $this->feeRepository->save($fee);

        return MembershipFeeDTO::fromEntity($fee);
    }

    public function find(string $id): ?MembershipFeeDTO
    {
        $fee = $this->feeRepository->find(MembershipFeeId::fromString($id));

        return $fee !== null ? MembershipFeeDTO::fromEntity($fee) : null;
    }

    public function findOrFail(string $id): MembershipFeeDTO
    {
        $fee = $this->feeRepository->findOrFail(MembershipFeeId::fromString($id));

        return MembershipFeeDTO::fromEntity($fee);
    }

    /**
     * @return array<MembershipFeeDTO>
     */
    public function findByMembershipId(string $membershipId): array
    {
        $fees = $this->feeRepository->findByMembershipId(MembershipId::fromString($membershipId));

        return array_map(
            fn (MembershipFee $fee) => MembershipFeeDTO::fromEntity($fee),
            $fees
        );
    }

    /**
     * @return array<MembershipFeeDTO>
     */
    public function getOverdueFees(): array
    {
        $fees = $this->feeRepository->getOverdueFees();

        return array_map(
            fn (MembershipFee $fee) => MembershipFeeDTO::fromEntity($fee),
            $fees
        );
    }

    /**
     * @return array<MembershipFeeDTO>
     */
    public function getUnpaidFees(): array
    {
        $fees = $this->feeRepository->getUnpaidFees();

        return array_map(
            fn (MembershipFee $fee) => MembershipFeeDTO::fromEntity($fee),
            $fees
        );
    }

    public function delete(string $id): void
    {
        $this->feeRepository->delete(MembershipFeeId::fromString($id));
    }
}
