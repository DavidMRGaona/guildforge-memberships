<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;

final readonly class RevokeMemberRoleOnExpiration
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private MembershipRepositoryInterface $membershipRepository,
    ) {
    }

    /**
     * Handle the MembershipExpired event.
     */
    public function handle(MembershipExpired $event): void
    {
        // Check if role assignment is enabled
        if (!$this->isRoleAssignmentEnabled()) {
            return;
        }

        // Get the member
        $memberId = new MemberId($event->memberId);
        $member = $this->memberRepository->find($memberId);

        if ($member === null) {
            return;
        }

        // Check if member is linked to a user
        if (!$member->isLinkedToUser()) {
            return;
        }

        // Check if member has other active memberships
        $activeMembership = $this->membershipRepository->findActiveMembership($memberId);

        if ($activeMembership !== null) {
            // Member still has an active membership, do not revoke role
            return;
        }

        // Get the user
        $user = UserModel::find($member->userId);

        if ($user === null) {
            return;
        }

        // Get the configured role name
        $roleName = $this->getMemberRoleName();

        // Find the role
        $role = RoleModel::where('name', $roleName)->first();

        if ($role === null) {
            return;
        }

        // Revoke the role
        $user->roles()->detach($role->id);
    }

    /**
     * Check if role assignment is enabled in config.
     */
    private function isRoleAssignmentEnabled(): bool
    {
        return (bool) config('memberships.enable_role_assignment', false);
    }

    /**
     * Get the member role name from config.
     */
    private function getMemberRoleName(): string
    {
        return (string) config('memberships.member_role_name', 'member');
    }
}
