<?php

namespace Domain\Requests\Support;

use Illuminate\Support\Facades\Storage;

final class DigitalSignatureService
{
    private const SECRET_PATH = 'keys/uleam-signing.secret';

    private const PUBLIC_PATH = 'keys/uleam-signing.pub';

    /**
     * @param  array<string, mixed>  $claims
     * @return array{payload: string, signature: string, fingerprint: string}
     */
    public function sign(array $claims): array
    {
        $payload = $this->canonical($claims);
        $this->ensureKeys();

        if ($this->usesSodium()) {
            $secret = base64_decode((string) Storage::disk('local')->get(self::SECRET_PATH), true) ?: '';
            $binary = sodium_crypto_sign_detached($payload, $secret);
        } else {
            $binary = hash_hmac('sha256', $payload, $this->hmacKey(), true);
        }

        return [
            'payload' => $payload,
            'signature' => base64_encode($binary),
            'fingerprint' => $this->fingerprint(),
        ];
    }

    public function verify(string $payload, string $signature): bool
    {
        $binary = base64_decode($signature, true);
        if ($binary === false || $binary === '') {
            return false;
        }

        $this->ensureKeys();

        if ($this->usesSodium()) {
            $public = base64_decode((string) Storage::disk('local')->get(self::PUBLIC_PATH), true) ?: '';

            return sodium_crypto_sign_verify_detached($binary, $payload, $public);
        }

        $expected = hash_hmac('sha256', $payload, $this->hmacKey(), true);

        return hash_equals($expected, $binary);
    }

    public function fingerprint(): string
    {
        $this->ensureKeys();
        $material = Storage::disk('local')->exists(self::PUBLIC_PATH)
            ? (string) Storage::disk('local')->get(self::PUBLIC_PATH)
            : $this->hmacKey();

        return substr(hash('sha256', $material), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function canonical(array $claims): string
    {
        ksort($claims);

        return json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function ensureKeys(): void
    {
        if (Storage::disk('local')->exists(self::SECRET_PATH)) {
            return;
        }

        if ($this->usesSodium()) {
            $pair = sodium_crypto_sign_keypair();
            Storage::disk('local')->put(self::SECRET_PATH, base64_encode(sodium_crypto_sign_secretkey($pair)));
            Storage::disk('local')->put(self::PUBLIC_PATH, base64_encode(sodium_crypto_sign_publickey($pair)));

            return;
        }

        Storage::disk('local')->put(self::SECRET_PATH, 'hmac');
        Storage::disk('local')->put(self::PUBLIC_PATH, hash('sha256', $this->hmacKey()));
    }

    private function usesSodium(): bool
    {
        return function_exists('sodium_crypto_sign_keypair')
            && function_exists('sodium_crypto_sign_detached');
    }

    private function hmacKey(): string
    {
        return (string) config('app.key');
    }
}
