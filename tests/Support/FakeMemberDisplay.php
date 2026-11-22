<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Support;

use App\Application\Profiles\Services\ProfileMembershipDisplayInterface;
use App\Domain\Profiles\Exceptions\ProfileMembershipDisplayUnavailableException;
use InvalidArgumentException;

/**
 * Which accounts show their membership, as the profile page would say: everybody unless hidden.
 * Like the core's service, it refuses more than MAX_IDS accounts in one call. The module's tests
 * load it with require_once: only src/ is autoloaded.
 */
final class FakeMemberDisplay implements ProfileMembershipDisplayInterface
{
    /** @var array<string, true> */
    public array $hidden = [];

    public bool $failing = false;

    public function showsMembership(array $userIds): array
    {
        if (count($userIds) > self::MAX_IDS) {
            throw new InvalidArgumentException('Too many accounts asked about at once.');
        }

        if ($this->failing) {
            throw ProfileMembershipDisplayUnavailableException::unreadable();
        }

        $answer = [];

        foreach ($userIds as $userId) {
            $answer[strtolower($userId)] = ! isset($this->hidden[strtolower($userId)]);
        }

        return $answer;
    }
}
