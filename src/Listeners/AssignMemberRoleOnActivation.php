<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use Modules\Memberships\Domain\Events\MembershipActivated;
use Modules\Memberships\Infrastructure\Services\MemberRoleSynchronizer;

final readonly class AssignMemberRoleOnActivation
{
    public function __construct(
        private MemberRoleSynchronizer $roles,
    ) {
    }

    public function handle(MembershipActivated $event): void
    {
        $this->roles->syncMember($event->memberId);
    }
}
