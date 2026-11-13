<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Situation;

final readonly class SituationMembershipDTO
{
    public function __construct(
        public string $status,
        public string $periodType,
        public string $startsOn,
        public string $endsOn,
    ) {}

    /**
     * @return array{status: string, periodType: string, startsOn: string, endsOn: string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'periodType' => $this->periodType,
            'startsOn' => $this->startsOn,
            'endsOn' => $this->endsOn,
        ];
    }
}
