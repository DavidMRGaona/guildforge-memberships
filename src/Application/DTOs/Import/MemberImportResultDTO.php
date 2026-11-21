<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Import;

/**
 * The outcome of an import: either every new row was created (with its period and fee) or,
 * when the report does not allow it, nothing was.
 */
final readonly class MemberImportResultDTO
{
    public function __construct(
        public bool $imported,
        public MemberImportPreviewDTO $report,
        public int $createdCount = 0,
    ) {}
}
