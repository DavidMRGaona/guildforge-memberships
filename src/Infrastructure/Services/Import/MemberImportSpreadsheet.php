<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services\Import;

use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Application\Spreadsheets\Services\SpreadsheetReaderInterface;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use Modules\Memberships\Application\DTOs\Import\MemberImportOptionsDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportPreviewDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowDTO;
use Modules\Memberships\Application\Services\MemberImportServiceInterface;

/**
 * Reads an uploaded `.csv` or `.xlsx` with the core's reader into the rows the import service
 * takes, and previews them. Bound per request, so a file read or previewed twice in one
 * request (the field's check, the preview and the import button) is read and previewed once;
 * a later request reads it again, so nothing outlives the request it was worked out in.
 */
final class MemberImportSpreadsheet
{
    public const int MAX_BYTES = 2 * 1024 * 1024;

    /** @var array<string, list<MemberImportRowDTO>> */
    private array $read = [];

    /** @var array<string, MemberImportPreviewDTO> */
    private array $previews = [];

    public function __construct(
        private readonly SpreadsheetReaderInterface $reader,
        private readonly MemberImportServiceInterface $imports,
    ) {}

    /**
     * What importing the file would do now.
     *
     * @throws SpreadsheetReadException when the file cannot be read as a spreadsheet
     */
    public function preview(string $path, bool $linkByEmail): MemberImportPreviewDTO
    {
        $key = $this->keyOf($path);
        $rows = $this->rows($path);

        if ($key === null) {
            return $this->imports->preview($rows, new MemberImportOptionsDTO(linkByEmail: $linkByEmail));
        }

        return $this->previews[$key.'|'.($linkByEmail ? 'link' : 'none')]
            ??= $this->imports->preview($rows, new MemberImportOptionsDTO(linkByEmail: $linkByEmail));
    }

    /**
     * @return list<MemberImportRowDTO>
     *
     * @throws SpreadsheetReadException when the file cannot be read as a spreadsheet
     */
    public function rows(string $path): array
    {
        $key = $this->keyOf($path);

        if ($key !== null && array_key_exists($key, $this->read)) {
            return $this->read[$key];
        }

        $sheet = $this->reader->read($path, new SpreadsheetLimitsDTO(
            maxRows: MemberImportServiceInterface::MAX_ROWS,
            maxBytes: self::MAX_BYTES,
        ));

        $rows = [];

        foreach ($sheet->rows as $row) {
            $values = [];

            // A numeric header arrives as an int key: the service looks headers up as text
            foreach ($row->values as $header => $value) {
                $values[(string) $header] = $value;
            }

            $rows[] = new MemberImportRowDTO($row->number, $values);
        }

        if ($key !== null) {
            $this->read[$key] = $rows;
        }

        return $rows;
    }

    /** The file as it is now, so a file replaced under the same path is read again. */
    private function keyOf(string $path): ?string
    {
        clearstatcache(true, $path);
        $size = @filesize($path);
        $modified = @filemtime($path);

        return $size === false || $modified === false ? null : $path.'|'.$size.'|'.$modified;
    }
}
