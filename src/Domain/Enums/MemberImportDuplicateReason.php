<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Enums;

/**
 * Why a row is skipped: a member (deleted ones too) already has its number or its email.
 */
enum MemberImportDuplicateReason: string
{
    case MemberNumber = 'member_number';
    case Email = 'email';
}
