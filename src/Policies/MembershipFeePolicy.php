<?php

declare(strict_types=1);

namespace Modules\Memberships\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;

/**
 * Seeing a member's payments and managing them (new fee, record a payment, edit, delete) are
 * separate: who answers members' questions does not have to handle the money.
 */
final class MembershipFeePolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'memberships:fees.view_any');
    }

    public function view(UserModel $user, MembershipFeeModel $fee): bool
    {
        return $this->authorize($user, 'memberships:fees.view_any');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'memberships:fees.manage');
    }

    public function update(UserModel $user, MembershipFeeModel $fee): bool
    {
        return $this->authorize($user, 'memberships:fees.manage');
    }

    public function delete(UserModel $user, MembershipFeeModel $fee): bool
    {
        return $this->authorize($user, 'memberships:fees.manage');
    }
}
