<?php

namespace App\Services\Qr;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Token = base64url(payload) . "." . base64url(HMAC-SHA256(payload))
 * payload = {"u": user_id, "n": nonce, "e": expira (unix)}
 */
class QrTokenService
{
    /** @return array{token: string, expires_at: int, ttl: int} */
    public function issue(User $user): array
    {
        $ttl = (int) config('qr.ttl');
        $expiresAt = time() + $ttl;

        $payload = $this->base64UrlEncode(json_encode([
            'u' => $user->id,
            'n' => bin2hex(random_bytes(16)),
            'e' => $expiresAt,
        ], JSON_THROW_ON_ERROR));

        return [
            'token' => $payload . '.' . $this->sign($payload),
            'expires_at' => $expiresAt,
            'ttl' => $ttl,
        ];
    }

    /**
     * Verifica firma, vigencia y que el nonce no se haya usado.
     * Al validar correctamente, el nonce queda consumido (un solo uso).
     */
    public function validate(string $token): QrValidation
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return QrValidation::invalid('malformed');
        }

        [$payload, $signature] = $parts;

        // La firma se comprueba ANTES de interpretar el contenido
        if (! hash_equals($this->sign($payload), $signature)) {
            return QrValidation::invalid('bad_signature');
        }

        $json = $this->base64UrlDecode($payload);
        $data = $json === null ? null : json_decode($json, true);

        if (! is_array($data)
            || ! is_int($data['u'] ?? null)
            || ! is_string($data['n'] ?? null)
            || ! is_int($data['e'] ?? null)) {
            return QrValidation::invalid('malformed');
        }

        if ($data['e'] < time()) {
            return QrValidation::invalid('expired');
        }

        // Anti-replay atómico: la PK de used_nonces rechaza el segundo intento
        $inserted = DB::table('used_nonces')->insertOrIgnore([
            'nonce' => $data['n'],
            'expires_at' => date('Y-m-d H:i:s', $data['e']),
        ]);

        if ($inserted === 0) {
            return QrValidation::invalid('replayed');
        }

        return QrValidation::valid($data['u'], $data['n']);
    }

    /** Borra nonces ya vencidos (un token vencido se rechaza igual por 'expired'). */
    public function purgeExpiredNonces(): int
    {
        return DB::table('used_nonces')
            ->where('expires_at', '<', date('Y-m-d H:i:s'))
            ->delete();
    }

    private function sign(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->key(), true));
    }

    private function key(): string
    {
        return config('qr.secret')
            ?: hash_hmac('sha256', 'qr-token-v1', (string) config('app.key'));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        $data = strtr($data, '-_', '+/');
        $data = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        $decoded = base64_decode($data, true);

        return $decoded === false ? null : $decoded;
    }
}
