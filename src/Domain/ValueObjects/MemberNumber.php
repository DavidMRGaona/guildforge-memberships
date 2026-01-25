<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\ValueObjects;

use Modules\Memberships\Domain\Exceptions\InvalidMemberNumberException;
use Stringable;

final readonly class MemberNumber implements Stringable
{
    private const string PATTERN = '/^\d{4}-\d{4}$/';

    public string $value;

    public function __construct(string $value)
    {
        if (! self::isValid($value)) {
            throw InvalidMemberNumberException::invalidFormat($value);
        }

        $this->value = $value;
    }

    public static function generate(int $year, int $sequence): self
    {
        $value = sprintf('%04d-%04d', $year, $sequence);

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    public function year(): int
    {
        return (int) substr($this->value, 0, 4);
    }

    public function sequence(): int
    {
        return (int) substr($this->value, 5, 4);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
