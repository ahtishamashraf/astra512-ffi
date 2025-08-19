<?php
require __DIR__.'/../vendor/autoload.php';

use Astra512\Astra512;
use Astra512\Password;

$astra = Astra512::create(getenv('ASTRA512_LIB') ?: null);

// Derive key from password (store $salt with the ciphertext)
$password = 'correct horse battery staple';
[$salt, $key] = Password::deriveKey($password);

[$ct, $tag] = $astra->encrypt($key, 'app:v1', 'secret');

// Later: re-derive the same key with the stored salt
$key2 = Password::deriveKeyWithSalt($password, $salt);
echo $astra->decrypt($key2, 'app:v1', $ct, $tag), PHP_EOL;
