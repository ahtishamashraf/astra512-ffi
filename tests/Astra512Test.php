<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Astra512\Astra512;
use Astra512\Key;
use Astra512\Password;

final class Astra512Test extends TestCase
{
    public function testWithRandomKey(): void
    {
        $lib = getenv('ASTRA512_LIB');
        if (!$lib) {
            $this->markTestSkipped('Set ASTRA512_LIB to run test');
        }
        $astra = Astra512::create($lib);
        $key = Key::generate();
        [$ct, $tag] = $astra->encrypt($key, "app:v1", "hello");
        $out = $astra->decrypt($key, "app:v1", $ct, $tag);
        $this->assertSame("hello", $out);
    }

    public function testWithPassword(): void
    {
        $lib = getenv('ASTRA512_LIB');
        if (!$lib) {
            $this->markTestSkipped('Set ASTRA512_LIB to run test');
        }
        $astra = Astra512::create($lib);
        [$salt, $key] = Password::deriveKey("pw");
        [$ct, $tag] = $astra->encrypt($key, "ns", "data");
        $key2 = Password::deriveKeyWithSalt("pw", $salt);
        $out = $astra->decrypt($key2, "ns", $ct, $tag);
        $this->assertSame("data", $out);
    }
}
