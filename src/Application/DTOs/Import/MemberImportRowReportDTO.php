<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Import;

use Modules\Memberships\Domain\Enums\MemberImportDuplicateReason;
use Modules\Memberships\Domain\Enums\MemberImportRowStatus;

/**
 * What the import does with one row. Holds the member's name for the panel to show; it is
 * never written to the log.
 */
final readonly class MemberImportRowReportDTO
{
    /**
     * @param  array<string, list<string>>  $errors  translated messages by column code
     *                                               (MemberImportColumn values)
     * @param  string|null  $memberNumber  the number the member gets (new rows) or the one
     *                                     the row gives
     */
    public function __construct(
        public int $line,
        public MemberImportRowStatus $status,
        public ?MemberImportDuplicateReason $duplicateReason = null,
        public array $errors = [],
        public ?string $memberNumber = null,
        public ?string $fullName = null,
        public bool $hasPeriod = false,
        public bool $hasFee = false,
        public ?string $linkedUserId = null,
        public ?string $linkedUserName = null,
    ) {}

    /**
     * @return array{line: int, status: string, duplicateReason: string|null, errors: array<string, list<string>>, memberNumber: string|null, fullName: string|null, hasPeriod: bool, hasFee: bool, linkedUserId: string|null, linkedUserName: string|null}
     */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'status' => $this->status->value,
            'duplicateReason' => $this->duplicateReason?->value,
            'errors' => $this->errors,
            'memberNumber' => $this->memberNumber,
            'fullName' => $this->fullName,
            'hasPeriod' => $this->hasPeriod,
            'hasFee' => $this->hasFee,
            'linkedUserId' => $this->linkedUserId,
            'linkedUserName' => $this->linkedUserName,
        ];
    }
}
