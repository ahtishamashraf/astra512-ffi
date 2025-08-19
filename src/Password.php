<?php
declare(strict_types=1);

namespace Astra512;

final class Password
{
    /**
     * Derive a 32-byte key from a password, returning [salt, key].
     * Salt is 16 random bytes; store it alongside the ciphertext so you can re-derive the key.
     */
    public static function deriveKey(string $password, int $opslimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, int $memlimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE): array
    {
        $salt = random_bytes(16);
        $key = sodium_crypto_pwhash(
            32,
            $password,
            $salt,
            $opslimit,
            $memlimit,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
        return [$salt, $key];
    }

    /**
     * Re-derive a 32-byte key using an existing salt (16 bytes).
     */
    public static function deriveKeyWithSalt(string $password, string $salt, int $opslimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, int $memlimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE): string
    {
        if (strlen($salt) !== 16) {
            throw new \RuntimeException('salt must be 16 bytes');
        }
        return sodium_crypto_pwhash(
            32,
            $password,
            $salt,
            $opslimit,
            $memlimit,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }
}
