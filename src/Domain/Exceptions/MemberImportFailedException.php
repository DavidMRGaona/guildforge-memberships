<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Exceptions;

use RuntimeException;

/**
 * The database refused a write while importing members (a member number taken meanwhile,
 * a lost connection…). Carries neither the query, its values nor the original exception,
 * so no personal data of the rows reaches a log or a failed job: only the SQLSTATE.
 */
final class MemberImportFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $sqlState,
    ) {
        parent::__construct(sprintf('The member import could not be saved (SQLSTATE %s); nothing was created', $sqlState));
    }
}
