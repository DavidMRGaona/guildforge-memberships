<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services\Import;

use Modules\Memberships\Application\Services\MemberNumberGeneratorInterface;
use Modules\Memberships\Domain\Exceptions\InvalidMemberNumberException;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;

/**
 * Hands out the numbers of the rows that give none, before anything is written, so the
 * preview shows the numbers the import gives. Each year starts where the generator says
 * (after every stored number, deleted members included) and skips the numbers other rows
 * of the same file give.
 */
final class MemberNumberPlanner
{
    private const int LAST_SEQUENCE = 9999;

    /** @var array<int, int> year → next sequence to try */
    private array $next = [];

    /** @var array<string, true> */
    private readonly array $reserved;

    /**
     * @param  list<string>  $reserved  numbers given by the file's rows
     */
    public function __construct(
        private readonly MemberNumberGeneratorInterface $generator,
        array $reserved,
    ) {
        $this->reserved = array_fill_keys($reserved, true);
    }

    /** The next free number of the year, or null when the year has none left. */
    public function next(int $year): ?MemberNumber
    {
        $sequence = $this->next[$year] ??= $this->firstSequence($year);

        while ($sequence <= self::LAST_SEQUENCE && isset($this->reserved[sprintf('%04d-%04d', $year, $sequence)])) {
            $sequence++;
        }

        if ($sequence > self::LAST_SEQUENCE) {
            $this->next[$year] = $sequence;

            return null;
        }

        $this->next[$year] = $sequence + 1;

        return MemberNumber::generate($year, $sequence);
    }

    private function firstSequence(int $year): int
    {
        try {
            return $this->generator->generateForYear($year)->sequence();
        } catch (InvalidMemberNumberException) {
            // The year already reached its last number
            return self::LAST_SEQUENCE + 1;
        }
    }
}
