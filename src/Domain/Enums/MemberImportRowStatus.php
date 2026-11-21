<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Enums;

/**
 * What an import does with a row: creates it, skips it because the member already exists,
 * or cannot read it (and then imports nothing at all).
 */
enum MemberImportRowStatus: string
{
    case New = 'new';
    case Duplicate = 'duplicate';
    case Invalid = 'invalid';
}
