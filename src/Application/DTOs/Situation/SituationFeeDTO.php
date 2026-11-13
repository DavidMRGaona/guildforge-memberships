<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Situation;

final readonly class SituationFeeDTO
{
    public function __construct(
        public int $amountCents,
        public string $currency,
        public string $dueOn,
        public bool $overdue,
    ) {}

    /**
     * @return array{amountCents: int, currency: string, dueOn: string, overdue: bool}
     */
    public function toArray(): array
    {
        return [
            'amountCents' => $this->amountCents,
            'currency' => $this->currency,
            'dueOn' => $this->dueOn,
            'overdue' => $this->overdue,
        ];
    }
}
