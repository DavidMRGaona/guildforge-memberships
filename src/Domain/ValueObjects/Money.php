<?php

declare(strict_types=1);

namespace Modules\Memberships\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class Money implements Stringable
{
    public function __construct(
        public int $amount,
        public string $currency = 'EUR',
    ) {
    }

    public static function fromCents(int $cents, string $currency = 'EUR'): self
    {
        return new self($cents, $currency);
    }

    public static function fromAmount(float|string $amount, string $currency = 'EUR'): self
    {
        $cents = (int) round((float) $amount * 100);

        return new self($cents, $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function toCents(): int
    {
        return $this->amount;
    }

    public function toAmount(): float
    {
        return $this->amount / 100;
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other, 'add');

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other, 'subtract');

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float|int $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return sprintf('%.2f %s', $this->toAmount(), $this->currency);
    }

    private function ensureSameCurrency(self $other, string $operation): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot {$operation} money with different currencies: {$this->currency} and {$other->currency}"
            );
        }
    }
}
