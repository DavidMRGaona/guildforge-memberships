<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs\Import;

/**
 * An account an imported member can be linked to.
 */
final readonly class LinkableAccountDTO
{
    /**
     * @param  bool  $holdsAssociationRole  the account holds the association member role
     *                                      now (stored, whoever gave it)
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $holdsAssociationRole = false,
    ) {}
}
