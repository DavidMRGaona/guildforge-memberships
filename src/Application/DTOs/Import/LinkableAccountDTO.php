<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Import;

/**
 * An account an imported member can be linked to.
 */
final readonly class LinkableAccountDTO
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
