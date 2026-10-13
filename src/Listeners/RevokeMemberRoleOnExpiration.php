<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

final readonly class RevokeMemberRoleOnExpiration
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private MembershipRepositoryInterface $membershipRepository,
        private AuthorizationServiceInterface $authorization,
        private MembershipSettingsReader $settings,
    ) {
    }

    public function handle(MembershipExpired $event): void
    {
        if (! $this->settings->roleAssignmentEnabled()) {
            return;
        }

        $memberId = new MemberId($event->memberId);
        $member = $this->memberRepository->find($memberId);

        if ($member === null || ! $member->isLinkedToUser()) {
            return;
        }

        // The member keeps the role while another membership is active
        if ($this->membershipRepository->findActiveMembership($memberId) !== null) {
            return;
        }

        $user = UserModel::find($member->userId);

        if ($user === null) {
            return;
        }

        // Through the authorization service, which clears the user's cached roles
        $this->authorization->removeRole($user, $this->settings->memberRoleName());
    }
}
