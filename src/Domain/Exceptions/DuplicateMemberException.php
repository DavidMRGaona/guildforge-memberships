<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Exceptions;

use DomainException;

final class DuplicateMemberException extends DomainException
{
    public static function emailAlreadyExists(string $email): self
    {
        return new self("A member with email '{$email}' already exists");
    }

    public static function memberNumberAlreadyExists(string $memberNumber): self
    {
        return new self("A member with member number '{$memberNumber}' already exists");
    }
}
