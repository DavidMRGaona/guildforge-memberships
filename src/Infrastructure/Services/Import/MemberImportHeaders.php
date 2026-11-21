<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services\Import;

use Modules\Memberships\Domain\Enums\MemberImportColumn;

/**
 * The columns the headers of a file name. A header names a column by its label in the
 * current locale or in Spanish, or by its code.
 */
final readonly class MemberImportHeaders
{
    /**
     * @param  array<string, MemberImportColumn>  $columns  header → column
     * @param  list<string>  $unknown  headers naming no column
     * @param  list<string>  $repeated  headers naming a column an earlier header names
     */
    private function __construct(
        public array $columns,
        public array $unknown,
        public array $repeated,
    ) {}

    /**
     * @param  list<string>  $headers  in the order of the file
     */
    public static function resolve(array $headers): self
    {
        $names = self::names();
        $columns = [];
        $taken = [];
        $unknown = [];
        $repeated = [];

        foreach ($headers as $header) {
            $column = $names[MemberImportText::normalise($header)] ?? null;

            if ($column === null) {
                $unknown[] = $header;
            } elseif (isset($taken[$column->value])) {
                $repeated[] = $header;
            } else {
                $taken[$column->value] = true;
                $columns[$header] = $column;
            }
        }

        return new self($columns, $unknown, $repeated);
    }

    public function columnFor(string $header): ?MemberImportColumn
    {
        return $this->columns[$header] ?? null;
    }

    /**
     * @return array<string, MemberImportColumn> normalised name → column
     */
    private static function names(): array
    {
        $names = [];

        foreach (MemberImportColumn::cases() as $column) {
            foreach ([$column->value, $column->label(), $column->label('es')] as $name) {
                $names[MemberImportText::normalise($name)] ??= $column;
            }
        }

        return $names;
    }
}
