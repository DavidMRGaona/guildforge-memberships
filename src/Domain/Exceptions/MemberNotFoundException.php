<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Exceptions;

use DomainException;

final class MemberNotFoundException extends DomainException
{
    public static function notFoundById(string $id): self
    {
        return new self("Member not found with ID: {$id}");
    }

    public static function notFoundByMemberNumber(string $memberNumber): self
    {
        return new self("Member not found with member number: {$memberNumber}");
    }

    public static function notFoundByEmail(string $email): self
    {
        return new self("Member not found with email: {$email}");
    }
}
