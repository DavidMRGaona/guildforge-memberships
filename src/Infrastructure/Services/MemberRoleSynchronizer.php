<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Modules\Memberships\Application\DTOs\RoleSyncSummaryDTO;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;

/**
 * Keeps the association member role of a user (chosen in the core site settings, "socio"
 * by default) in line with their members: the user holds it while a (not deleted) member
 * linked to them qualifies under Member::qualifiesForAssociationRole().
 *
 * Roles change through AuthorizationServiceInterface, which clears the cached roles. Does
 * nothing while role assignment is disabled, while the core role cannot be used (unset as
 * a base role, or missing) and never touches the base roles (admin, editor, member).
 */
final readonly class MemberRoleSynchronizer
{
    private const string ADDED = 'added';

    private const string REMOVED = 'removed';

    private const string UNCHANGED = 'unchanged';

    public function __construct(
        private AuthorizationServiceInterface $authorization,
        private AssociationMembershipServiceInterface $associationMembership,
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

        $roleName = $this->usableRoleName();

        if ($roleName !== null) {
            $this->apply($userId, $roleName, dryRun: false);
        }
    }

    /**
     * Synchronise every user linked to a member, deleted members included.
     *
     * @return RoleSyncSummaryDTO|null null when the core role cannot be used (nothing is checked)
     */
    public function syncAll(bool $dryRun = false): ?RoleSyncSummaryDTO
    {
        $roleName = $this->usableRoleName();

        if ($roleName === null) {
            return null;
        }

        $userIds = MemberModel::withTrashed()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $added = 0;
        $removed = 0;

        foreach ($userIds as $userId) {
            $change = $this->apply((string) $userId, $roleName, $dryRun);

            if ($change === self::ADDED) {
                $added++;
            } elseif ($change === self::REMOVED) {
                $removed++;
            }
        }

        return new RoleSyncSummaryDTO($userIds->count(), $added, $removed);
    }

    /**
     * @return self::ADDED|self::REMOVED|self::UNCHANGED
     */
    private function apply(string $userId, string $roleName, bool $dryRun): string
    {
        $user = UserModel::find($userId);

        if ($user === null) {
            return self::UNCHANGED;
        }

        // Read from the database, not the role cache, so a run reports what it really changes
        $holdsRole = $user->roles()->where('name', $roleName)->exists();
        $qualifies = $this->qualifies($userId);

        if ($qualifies && ! $holdsRole) {
            if (! $dryRun) {
                $this->authorization->assignRole($user, $roleName);
            }

            return self::ADDED;
        }

        if (! $qualifies && $holdsRole) {
            if (! $dryRun) {
                $this->authorization->removeRole($user, $roleName);
            }

            return self::REMOVED;
        }

        return self::UNCHANGED;
    }

    /**
     * Whether any (not deleted) member linked to the user qualifies.
     */
    private function qualifies(string $userId): bool
    {
        $permanentTypes = $this->settings->permanentMemberTypes();

        return MemberModel::query()
            ->where('user_id', $userId)
            ->withExists([
                'memberships as has_active_membership' => fn (Builder $query): Builder => $query->where('status', MembershipStatus::Active->value),
            ])
            ->get()
            ->contains(fn (MemberModel $member): bool => Member::qualifiesForAssociationRole(
                $member->status,
                $member->member_type,
                (bool) $member->getAttribute('has_active_membership'),
                $permanentTypes,
            ));
    }

    /**
     * The association member role from the core, or null (with a warning) when it cannot be
     * used: unset as a base role, a base role anyway, or missing.
     */
    private function usableRoleName(): ?string
    {
        $roleName = $this->associationMembership->roleName();

        if ($roleName === null) {
            Log::warning('Memberships: the association member role setting holds a base role; association member roles were not synchronised');

            return null;
        }

        // Defence in depth: the core never returns a base role, but granting or revoking one
        // would hand out the panel or strip every registered user of their role
        if (in_array(mb_strtolower(trim($roleName)), AssociationMembershipServiceInterface::FORBIDDEN_ROLES, true)) {
            Log::warning("Memberships: \"{$roleName}\" is a base role and is never granted or revoked by the module");

            return null;
        }

        if (! RoleModel::where('name', $roleName)->exists()) {
            Log::warning("Memberships: the association member role \"{$roleName}\" does not exist; association member roles were not synchronised");

            return null;
        }

        return $roleName;
    }
}
