<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Memberships\Domain\Events\MembershipActivated;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;

final readonly class AssignMemberRoleOnActivation
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
    ) {
    }

    /**
     * Handle the MembershipActivated event.
     */
    public function handle(MembershipActivated $event): void
    {
        // Check if role assignment is enabled
        if (!$this->isRoleAssignmentEnabled()) {
            return;
        }

        // Get the member
        $member = $this->memberRepository->find(new MemberId($event->memberId));

        if ($member === null) {
            return;
        }

        // Check if member is linked to a user
        if (!$member->isLinkedToUser()) {
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

        // Assign the role if not already assigned
        if (!$user->roles()->where('name', $roleName)->exists()) {
            $user->roles()->attach($role->id);
        }
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
