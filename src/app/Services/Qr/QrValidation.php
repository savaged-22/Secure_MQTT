<?php

namespace App\Services\Qr;

final readonly class QrValidation
{
    private function __construct(
        public bool $ok,
        public string $reason,
        public ?int $userId = null,
        public ?string $nonce = null,
    ) {
    }

    public static function valid(int $userId, string $nonce): self
    {
        return new self(true, 'ok', $userId, $nonce);
    }

    /** Motivos: malformed | bad_signature | expired | replayed */
    public static function invalid(string $reason): self
    {
        return new self(false, $reason);
    }
}
