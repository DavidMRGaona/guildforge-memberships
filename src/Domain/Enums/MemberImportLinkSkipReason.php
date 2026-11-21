<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\Enums;

/**
 * Why a new row that matches an account is imported without linking it.
 */
enum MemberImportLinkSkipReason: string
{
    /** Linking would take the association member role the account holds now. */
    case WouldRemoveAssociationRole = 'would_remove_association_role';

    public function message(): string
    {
        return (string) __('memberships::memberships.import.link_skipped.'.$this->value);
    }
}
