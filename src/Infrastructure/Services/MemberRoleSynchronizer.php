<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;

/**
 * Keeps the member role of a user in line with their memberships: the role is held
 * while a (not deleted) member linked to the user has an active membership.
 *
 * Roles change through AuthorizationServiceInterface, which clears the user's cached roles.
 * Does nothing while role assignment is disabled.
 */
final readonly class MemberRoleSynchronizer
{
    public function __construct(
        private AuthorizationServiceInterface $authorization,
        private MembershipSettingsReader $settings,
    ) {
    }

    public function syncMember(string $memberId): void
    {
        $userId = MemberModel::withTrashed()->whereKey($memberId)->value('user_id');

        if (is_string($userId)) {
            $this->syncUser($userId);
        }
    }

    public function syncUser(string $userId): void
    {
        if (! $this->settings->roleAssignmentEnabled()) {
            return;
        }

        $user = UserModel::find($userId);

        if ($user === null) {
            return;
        }

        $roleName = $this->settings->memberRoleName();

        if (! $this->hasActiveMembership($userId)) {
            $this->authorization->removeRole($user, $roleName);

            return;
        }

        if (RoleModel::where('name', $roleName)->exists()) {
            $this->authorization->assignRole($user, $roleName);
        }
    }

    /**
     * Synchronise every user linked to a member, deleted members included.
     *
     * @return int the number of users checked
     */
    public function syncAll(): int
    {
        $userIds = MemberModel::withTrashed()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $this->syncUser((string) $userId);
        }

        return $userIds->count();
    }

    private function hasActiveMembership(string $userId): bool
    {
        return MemberModel::query()
            ->where('user_id', $userId)
            ->whereHas('memberships', fn (Builder $query): Builder => $query->where('status', MembershipStatus::Active->value))
            ->exists();
    }
}
