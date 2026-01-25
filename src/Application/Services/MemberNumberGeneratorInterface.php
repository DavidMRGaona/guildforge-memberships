<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\Services;

use Modules\Memberships\Domain\ValueObjects\MemberNumber;

interface MemberNumberGeneratorInterface
{
    /**
     * Generate a new member number for the current year.
     */
    public function generate(): MemberNumber;

    /**
     * Generate a new member number for a specific year.
     */
    public function generateForYear(int $year): MemberNumber;
}
