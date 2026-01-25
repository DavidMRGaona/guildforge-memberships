<?php

declare(strict_types=1);

namespace Modules\Memberships\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;

final class MemberPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'memberships:members.view_any');
    }

    public function view(UserModel $user, MemberModel $member): bool
    {
        return $this->authorize($user, 'memberships:members.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'memberships:members.create');
    }

    public function update(UserModel $user, MemberModel $member): bool
    {
        return $this->authorize($user, 'memberships:members.update');
    }

    public function delete(UserModel $user, MemberModel $member): bool
    {
        return $this->authorize($user, 'memberships:members.delete');
    }

    public function restore(UserModel $user, MemberModel $member): bool
    {
        return $this->authorize($user, 'memberships:members.delete');
    }

    public function forceDelete(UserModel $user, MemberModel $member): bool
    {
        return $this->authorize($user, 'memberships:members.delete');
    }
}
