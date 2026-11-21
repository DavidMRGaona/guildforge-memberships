<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services\Import;

use Illuminate\Support\Str;

/**
 * How headers and names typed in a spreadsheet are compared: without case, accents or
 * extra spaces.
 */
final class MemberImportText
{
    public static function normalise(string $text): string
    {
        return Str::of($text)->ascii()->lower()->squish()->toString();
    }
}
