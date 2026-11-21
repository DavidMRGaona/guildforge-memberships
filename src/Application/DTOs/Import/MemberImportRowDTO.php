<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Import;

/**
 * A row as a spreadsheet reader hands it over: the text of each cell under its header, and
 * the line of the file (for the messages).
 */
final readonly class MemberImportRowDTO
{
    /**
     * @param  array<string, string>  $values  header → text
     */
    public function __construct(
        public int $line,
        public array $values,
    ) {}
}
