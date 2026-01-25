<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Exceptions;

use DomainException;

final class MembershipFeeNotFoundException extends DomainException
{
    public static function notFoundById(string $id): self
    {
        return new self("Membership fee not found with ID: {$id}");
    }
}
