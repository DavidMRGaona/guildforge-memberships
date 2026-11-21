<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Import;

/**
 * The outcome of an import: either every new row was created (with its period and fee) or,
 * when the report does not allow it, nothing was. A busy result means another import was
 * running: nothing was checked or created, and it can be tried again in a moment.
 */
final readonly class MemberImportResultDTO
{
    public function __construct(
        public bool $imported,
        public MemberImportPreviewDTO $report,
        public int $createdCount = 0,
        public bool $busy = false,
    ) {}

    public static function busy(): self
    {
        return new self(imported: false, report: new MemberImportPreviewDTO(rows: []), busy: true);
    }

    public function message(): ?string
    {
        return $this->busy ? (string) __('memberships::memberships.import.busy') : null;
    }
}
