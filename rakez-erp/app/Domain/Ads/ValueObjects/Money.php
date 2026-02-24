<?php

namespace App\Domain\Ads\ValueObjects;

final readonly class Money
{
    public function __construct(
        public float $amount,
        public string $currency = 'USD',
    ) {}

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, $currency);
    }

    public function negate(): self
    {
        return new self(-$this->amount, $this->currency);
    }
}
