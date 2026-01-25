<?php

declare(strict_types=1);

namespace Modules\Memberships\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;

final class FeeStructurePolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'memberships:fee_structures.view_any');
    }

    public function view(UserModel $user, FeeStructureModel $feeStructure): bool
    {
        return $this->authorize($user, 'memberships:fee_structures.view');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'memberships:fee_structures.create');
    }

    public function update(UserModel $user, FeeStructureModel $feeStructure): bool
    {
        return $this->authorize($user, 'memberships:fee_structures.update');
    }

    public function delete(UserModel $user, FeeStructureModel $feeStructure): bool
    {
        return $this->authorize($user, 'memberships:fee_structures.delete');
    }
}
