<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Exceptions;

use DomainException;

final class MembershipNotFoundException extends DomainException
{
    public static function notFoundById(string $id): self
    {
        return new self("Membership not found with ID: {$id}");
    }

    public static function noActiveMembershipForMember(string $memberId): self
    {
        return new self("No active membership found for member with ID: {$memberId}");
    }
}
