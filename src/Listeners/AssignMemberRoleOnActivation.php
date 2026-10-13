<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Memberships\Domain\Events\MembershipActivated;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

final readonly class AssignMemberRoleOnActivation
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private AuthorizationServiceInterface $authorization,
        private MembershipSettingsReader $settings,
    ) {
    }

    public function handle(MembershipActivated $event): void
    {
        if (! $this->settings->roleAssignmentEnabled()) {
            return;
        }

        $member = $this->memberRepository->find(new MemberId($event->memberId));

        if ($member === null || ! $member->isLinkedToUser()) {
            return;
        }

        $user = UserModel::find($member->userId);

        if ($user === null) {
            return;
        }

        $roleName = $this->settings->memberRoleName();

        if (! RoleModel::where('name', $roleName)->exists()) {
            return;
        }

        // Through the authorization service, which clears the user's cached roles
        $this->authorization->assignRole($user, $roleName);
    }
}
