<?php

namespace App\Domain\Ads\ValueObjects;

final readonly class HashedIdentifier
{
    public function __construct(
        public string $type,
        public string $hashedValue,
        public bool $isPreHashed = false,
    ) {}
}
