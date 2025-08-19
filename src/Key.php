<?php
declare(strict_types=1);

namespace Astra512;

final class Key
{
    /**
     * Generate a random 32-byte key.
     */
    public static function generate(): string
    {
        return random_bytes(32);
    }

    /**
     * Save key to a file with restrictive permissions (600 on Unix).
     */
    public static function save(string $path, string $key): void
    {
        if (strlen($key) !== 32) {
            throw new \RuntimeException('key must be 32 bytes');
        }
        if (false === file_put_contents($path, $key, LOCK_EX)) {
            throw new \RuntimeException('failed to write key file');
        }
        // Best-effort permission tighten on Unix
        if (DIRECTORY_SEPARATOR === '/') {
            @chmod($path, 0o600);
        }
    }

    /**
     * Load key from a file.
     */
    public static function load(string $path): string
    {
        $key = @file_get_contents($path);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('invalid or missing key file');
        }
        return $key;
    }
}
