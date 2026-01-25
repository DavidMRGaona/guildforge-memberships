<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use Modules\Memberships\Application\Services\MemberNumberGeneratorInterface;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;

final readonly class MemberNumberGenerator implements MemberNumberGeneratorInterface
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
    ) {}

    public function generate(): MemberNumber
    {
        return $this->generateForYear((int) date('Y'));
    }

    public function generateForYear(int $year): MemberNumber
    {
        return $this->memberRepository->getNextMemberNumber($year);
    }
}
