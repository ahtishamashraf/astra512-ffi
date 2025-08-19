<?php
declare(strict_types=1);

namespace Astra512;

use FFI;
use RuntimeException;

final class Astra512
{
    private FFI $ffi;

    private function __construct(FFI $ffi)
    {
        $this->ffi = $ffi;
    }

    public static function create(?string $libPath = null): self
    {
        $cdef = <<<CDEF
        int astra512_encrypt(const char*, const char*, size_t,
                             const char*, size_t, unsigned char*, unsigned char*);
        int astra512_decrypt(const char*, const char*, size_t,
                             const char*, size_t, const char*, unsigned char*);
        CDEF;
        

        $candidates = [];

        if ($libPath) {
            $candidates[] = $libPath;
        }
        $env = getenv('ASTRA512_LIB');
        if ($env && $env !== '') {
            $candidates[] = $env;
        }

        $base = __DIR__ . '/../bin';
        $os  = PHP_OS_FAMILY;   // Windows|Linux|Darwin|BSD
        $arch = php_uname('m');

        $platforms = [];
        if ($os === 'Windows') {
            $platforms[] = 'windows-x64';
        } elseif ($os === 'Darwin' || $os === 'BSD') {
            $platforms[] = 'macos-universal';
        } else {
            $platforms[] = 'linux-' . $arch;
        }

        foreach ($platforms as $p) {
            $dir = $base . '/' . $p;
            $candidates[] = $dir . '/astra512.dll';
            $candidates[] = $dir . '/libastra512.dylib';
            $candidates[] = $dir . '/libastra512.so';
        }

        // System fallbacks
        $candidates[] = 'astra512.dll';
        $candidates[] = 'libastra512.dylib';
        $candidates[] = 'libastra512.so';

        $errors = [];
        foreach ($candidates as $candidate) {
            try {
                if ($candidate && @file_exists($candidate)) {
                    return new self(FFI::cdef($cdef, $candidate));
                }
                // try load even if not exists, in case loader paths can resolve it
                return new self(FFI::cdef($cdef, $candidate));
            } catch (\Throwable $e) {
                $errors[] = $candidate . ': ' . $e->getMessage();
            }
        }

        throw new RuntimeException(
            "ASTRA-512 native library could not be loaded. Tried:\n" . implode("\n", $errors) .
            "\nSet ASTRA512_LIB to the absolute path of your libastra512.*"
        );
    }

    /**
     * Encrypt using a 32-byte symmetric key.
     * @return array{0:string,1:string} [$ct,$tag]
     */
    public function encrypt(string $key32, string $aad, string $pt): array
    {
        if (strlen($key32) !== 32) {
            throw new RuntimeException('key must be 32 bytes');
        }
        $ct  = FFI::new('unsigned char[' . strlen($pt) . ']');
        $tag = FFI::new('unsigned char[16]');
        $rc = $this->ffi->astra512_encrypt($key32, $aad, strlen($aad), $pt, strlen($pt), $ct, $tag);
        if ($rc !== 0) {
            throw new RuntimeException('encrypt failed rc=' . $rc);
        }
        return [FFI::string($ct, strlen($pt)), FFI::string($tag, 16)];
    }

    /**
     * Decrypt using the same 32-byte key.
     */
    public function decrypt(string $key32, string $aad, string $ct, string $tag): string
    {
        if (strlen($key32) !== 32) {
            throw new RuntimeException('key must be 32 bytes');
        }
        if (strlen($tag) !== 16) {
            throw new RuntimeException('tag must be 16 bytes');
        }
        $pt = FFI::new('unsigned char[' . strlen($ct) . ']');
        $rc = $this->ffi->astra512_decrypt($key32, $aad, strlen($aad), $ct, strlen($ct), $tag, $pt);
        if ($rc !== 0) {
            throw new RuntimeException('auth failed rc=' . $rc);
        }
        return FFI::string($pt, strlen($ct));
    }
}
