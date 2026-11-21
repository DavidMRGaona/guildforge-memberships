<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Import;

final readonly class MemberImportOptionsDTO
{
    /**
     * @param  bool  $linkByEmail  link each new member to the only active, verified account
     *                             with the same email (off by default)
     */
    public function __construct(
        public bool $linkByEmail = false,
    ) {}
}
