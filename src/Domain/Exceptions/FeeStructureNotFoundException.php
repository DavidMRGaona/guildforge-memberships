<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Exceptions;

use DomainException;

final class FeeStructureNotFoundException extends DomainException
{
    public static function notFoundById(string $id): self
    {
        return new self("Fee structure not found with ID: {$id}");
    }
}
