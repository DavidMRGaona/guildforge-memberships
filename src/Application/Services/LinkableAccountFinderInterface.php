<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\Services;

use Modules\Memberships\Application\DTOs\Import\LinkableAccountDTO;

/**
 * Finds the accounts imported members may be linked to. An email gives an account only
 * when exactly one account (deactivated ones included) has it, compared without case, and
 * that account is active, not anonymized, has its email verified and is not linked to a
 * member yet. Any doubt gives no account.
 */
interface LinkableAccountFinderInterface
{
    /**
     * @param  list<string>  $emails
     * @return array<string, LinkableAccountDTO> by lower-case email; emails without an
     *                                           account to link are left out
     */
    public function findByEmails(array $emails): array;
}
