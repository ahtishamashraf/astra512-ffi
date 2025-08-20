FFI bindings for **ASTRA-512** (research SIV-style AEAD) with **key management** and **password-based key derivation** helpers.

> ⚠️ This is *research cryptography*. Do not use in production until independent review.

## Install
```bash
composer require yourvendor/astra512-ffi
```

## Native library
The FFI loader searches:
1) explicit path you pass to `Astra512::create($path)`  
2) `ASTRA512_LIB` env var (absolute path)  
3) vendored `bin/<platform>/`  
4) system names: `libastra512.so` / `libastra512.dylib` / `astra512.dll`

## Use with a **random 32-byte key**
```php
use Astra512\Astra512;
use Astra512\Key;

$astra = Astra512::create(getenv('ASTRA512_LIB') ?: null);
$key = Key::generate();                   // 32 random bytes
$aad = "app:v1";
$pt  = "hello";

[$ct, $tag] = $astra->encrypt($key, $aad, $pt);
$out = $astra->decrypt($key, $aad, $ct, $tag);
```

## Use with a **password** (derives a 32-byte key via Argon2id)
```php
use Astra512\Astra512;
use Astra512\Password;

$astra = Astra512::create(getenv('ASTRA512_LIB') ?: null);
$password = "correct horse battery staple";
[$salt, $key] = Password::deriveKey($password);   // returns [16-byte salt, 32-byte key]

[$ct, $tag] = $astra->encrypt($key, "app:v1", "secret");

// To decrypt later, re-derive the key with the saved salt:
$key2 = Password::deriveKeyWithSalt($password, $salt);
$out  = $astra->decrypt($key2, "app:v1", $ct, $tag);
```

### About *salt*
- **Encryption itself does not need a salt or nonce** (ASTRA-512 is SIV-style and deterministic).  
- **Salt is only for password-based key derivation** (Argon2id). Store the salt alongside the ciphertext so you can re-derive the same key to decrypt.

## Helpers
- `Key::generate()` → 32 random bytes
- `Key::save($path, $key)` / `Key::load($path)`
- `Password::deriveKey($password, $opslimit=?, $memlimit=?)` → returns `[salt, key]`
- `Password::deriveKeyWithSalt($password, $salt, ...)` → returns `key`

See `examples/` for complete flows.
# astra512-ffi
