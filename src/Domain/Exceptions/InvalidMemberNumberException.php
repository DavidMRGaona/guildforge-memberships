<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Exceptions;

use DomainException;

final class InvalidMemberNumberException extends DomainException
{
    public static function invalidFormat(string $value): self
    {
        return new self("Invalid member number format: {$value}");
    }
}
