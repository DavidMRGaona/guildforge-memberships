<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\Services;

use Modules\Memberships\Application\DTOs\MemberSituationDTO;

interface MemberSituationQueryInterface
{
    /**
     * The situation of the member record linked to the account, or null when the account has
     * none (or it was deleted): the profile tab is not shown then.
     */
    public function forUser(string $userId): ?MemberSituationDTO;
}
