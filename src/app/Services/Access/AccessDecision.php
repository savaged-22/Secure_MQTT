<?php

namespace App\Services\Access;

final readonly class AccessDecision
{
    private function __construct(
        public bool $granted,
        public string $reason,
    ) {
    }

    public static function grant(string $reason = 'granted'): self
    {
        return new self(true, $reason);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
