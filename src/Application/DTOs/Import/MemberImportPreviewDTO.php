<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Import;

use Modules\Memberships\Domain\Enums\MemberImportRowStatus;

/**
 * What an import would do with each row, and whether it can run: only with at least one new
 * row, and never with an invalid row, two headers for the same column or more rows than
 * allowed.
 */
final readonly class MemberImportPreviewDTO
{
    /**
     * @param  list<MemberImportRowReportDTO>  $rows  in the order of the file
     * @param  list<string>  $unknownColumns  headers that are not a column (ignored)
     * @param  list<string>  $repeatedColumns  headers naming a column another header already names
     * @param  bool  $rowLimitExceeded  more rows than MemberImportServiceInterface::MAX_ROWS
     *                                  (no row is checked then)
     */
    public function __construct(
        public array $rows,
        public array $unknownColumns = [],
        public array $repeatedColumns = [],
        public bool $rowLimitExceeded = false,
    ) {}

    public function newCount(): int
    {
        return $this->count(MemberImportRowStatus::New);
    }

    public function duplicateCount(): int
    {
        return $this->count(MemberImportRowStatus::Duplicate);
    }

    public function invalidCount(): int
    {
        return $this->count(MemberImportRowStatus::Invalid);
    }

    /** New rows that would be linked to an account. */
    public function linkedCount(): int
    {
        return count(array_filter(
            $this->rows,
            static fn (MemberImportRowReportDTO $row): bool => $row->status === MemberImportRowStatus::New && $row->linkedUserId !== null,
        ));
    }

    public function canImport(): bool
    {
        return ! $this->rowLimitExceeded
            && $this->repeatedColumns === []
            && $this->invalidCount() === 0
            && $this->newCount() > 0;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, unknownColumns: list<string>, repeatedColumns: list<string>, rowLimitExceeded: bool, counts: array{new: int, duplicate: int, invalid: int, linked: int}, canImport: bool}
     */
    public function toArray(): array
    {
        return [
            'rows' => array_map(static fn (MemberImportRowReportDTO $row): array => $row->toArray(), $this->rows),
            'unknownColumns' => $this->unknownColumns,
            'repeatedColumns' => $this->repeatedColumns,
            'rowLimitExceeded' => $this->rowLimitExceeded,
            'counts' => [
                'new' => $this->newCount(),
                'duplicate' => $this->duplicateCount(),
                'invalid' => $this->invalidCount(),
                'linked' => $this->linkedCount(),
            ],
            'canImport' => $this->canImport(),
        ];
    }

    private function count(MemberImportRowStatus $status): int
    {
        return count(array_filter(
            $this->rows,
            static fn (MemberImportRowReportDTO $row): bool => $row->status === $status,
        ));
    }
}
