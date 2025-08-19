<?php
require __DIR__.'/../vendor/autoload.php';

use Astra512\Astra512;
use Astra512\Key;

$astra = Astra512::create(getenv('ASTRA512_LIB') ?: null);

// Generate a key once and save it
$keyPath = __DIR__ . '/mykey.bin';
if (!file_exists($keyPath)) {
    Key::save($keyPath, Key::generate());
}
$key = Key::load($keyPath);

// Encrypt/decrypt
[$ct, $tag] = $astra->encrypt($key, 'app:v1', 'hello');
echo $astra->decrypt($key, 'app:v1', $ct, $tag), PHP_EOL;
