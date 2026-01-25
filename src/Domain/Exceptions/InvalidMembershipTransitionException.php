<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Exceptions;

use DomainException;
use Modules\Memberships\Domain\Enums\MembershipStatus;

final class InvalidMembershipTransitionException extends DomainException
{
    public static function alreadyActive(): self
    {
        return new self('Membership is already active');
    }

    public static function cannotTransition(MembershipStatus $from, MembershipStatus $to): self
    {
        return new self("Cannot transition from {$from->value} to {$to->value}");
    }
}
