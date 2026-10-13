<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use Modules\Memberships\Domain\Events\MembershipDeactivated;
use Modules\Memberships\Infrastructure\Services\MemberRoleSynchronizer;

/**
 * Removes the member role unless another active membership keeps it.
 */
final readonly class RevokeMemberRoleOnDeactivation
{
    public function __construct(
        private MemberRoleSynchronizer $roles,
    ) {
    }

    public function handle(MembershipDeactivated $event): void
    {
        $this->roles->syncMember($event->memberId);
    }
}
