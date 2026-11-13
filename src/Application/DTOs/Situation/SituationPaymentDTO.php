<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Situation;

final readonly class SituationPaymentDTO
{
    public function __construct(
        public int $amountCents,
        public string $currency,
        public string $paidOn,
    ) {}

    /**
     * @return array{amountCents: int, currency: string, paidOn: string}
     */
    public function toArray(): array
    {
        return [
            'amountCents' => $this->amountCents,
            'currency' => $this->currency,
            'paidOn' => $this->paidOn,
        ];
    }
}
