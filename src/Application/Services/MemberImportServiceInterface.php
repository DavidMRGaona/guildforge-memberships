<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\Services;

use Modules\Memberships\Application\DTOs\Import\MemberImportOptionsDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportPreviewDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportResultDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowDTO;

/**
 * Imports members, one per row, with their current period and its fee. Works on rows
 * already read from a file, so it does not care how the file was read.
 *
 * A row whose member number or email a member already has (deleted ones too) is skipped,
 * never updated; two rows of the same file with the same number or email are both invalid.
 * Nothing is mailed: overdue fees are found later by the daily process, as usual.
 */
interface MemberImportServiceInterface
{
    public const int MAX_ROWS = 1000;

    /**
     * What import() would do with each row, without writing anything.
     *
     * @param  list<MemberImportRowDTO>  $rows
     */
    public function preview(array $rows, MemberImportOptionsDTO $options): MemberImportPreviewDTO;

    /**
     * Checks every row again (never trusts an earlier preview) and, when the report allows
     * it, creates every new row in one transaction; otherwise creates nothing.
     *
     * @param  list<MemberImportRowDTO>  $rows
     */
    public function import(array $rows, MemberImportOptionsDTO $options): MemberImportResultDTO;
}
