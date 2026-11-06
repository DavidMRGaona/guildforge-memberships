<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;

/**
 * The person's account is linked to a member record that has not been deleted, whatever its
 * status: the account the module's membership emails go to (NotifiesMember).
 */
final readonly class HasMemberRecordCapability implements KnowledgeCapabilityInterface
{
    public function __construct(private MemberRepositoryInterface $members) {}

    public function key(): string
    {
        return 'memberships.has_member_record';
    }

    public function passes(object $user): bool
    {
        if (! $user instanceof Authenticatable) {
            return false;
        }

        return $this->members->findByUserId((string) $user->getAuthIdentifier()) instanceof Member;
    }
}
