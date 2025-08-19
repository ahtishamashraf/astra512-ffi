<?php
declare(strict_types=1);

// ======= CONFIG: set your C repo owner/name =======
const GH_OWNER = 'YOUR_GH_USER_OR_ORG';
const GH_REPO  = 'YOUR_C_REPO'; // e.g. "astra512"
// ================================================

function targetPath(): array {
    $base = __DIR__ . '/../bin';
    $os   = PHP_OS_FAMILY;   // Windows|Linux|Darwin|BSD
    $arch = php_uname('m');
    if ($os === 'Windows') {
        $dir = "$base/windows-x64"; @mkdir($dir, 0777, true);
        return [$dir . '/astra512.dll', 'astra512.dll'];
    } elseif ($os === 'Darwin' || $os === 'BSD') {
        $dir = "$base/macos-universal"; @mkdir($dir, 0777, true);
        return [$dir . '/libastra512.dylib', 'libastra512.dylib'];
    } else {
        $dir = "$base/linux-$arch"; @mkdir($dir, 0777, true);
        return [$dir . '/libastra512.so', 'libastra512.so'];
    }
}

function http_get(string $url, ?string $token, array $headers = []): string {
    $h = array_merge(['Accept: application/vnd.github+json','User-Agent: astra512-ffi-installer'], $headers);
    if ($token) $h[] = "Authorization: Bearer $token";
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true
        ]);
        $data = curl_exec($ch);
        if ($data === false) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException("curl error: $err"); }
        curl_close($ch);
        return $data;
    }
    $headers_str = "";
    foreach ($h as $line) $headers_str .= $line."\r\n";
    $ctx = stream_context_create(['http' => ['header' => $headers_str]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) throw new RuntimeException("download failed: $url");
    return $data;
}

function latestRelease(): array {
    $token = getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN') ?: null;
    $api = "https://api.github.com/repos/".GH_OWNER."/".GH_REPO."/releases/latest";
    $json = json_decode(http_get($api, $token), true);
    if (!is_array($json) || !isset($json['assets'])) throw new RuntimeException("cannot parse latest release info");
    return [$json, $token];
}

function downloadAsset(string $name, string $dest, string $token): void {
    $api = "https://api.github.com/repos/".GH_OWNER."/".GH_REPO."/releases/latest";
    $latest = json_decode(http_get($api, $token), true);
    foreach ($latest['assets'] as $a) {
        if (($a['name'] ?? '') === $name) {
            // Use asset id to get real binary download
            $assetId = $a['id'];
            $url = "https://api.github.com/repos/".GH_OWNER."/".GH_REPO."/releases/assets/$assetId";
            $bin = http_get($url, $token, ['Accept: application/octet-stream']);
            if (!@file_put_contents($dest, $bin)) throw new RuntimeException("write failed: $dest");
            if (DIRECTORY_SEPARATOR === '/') @chmod($dest, 0755);
            return;
        }
    }
    throw new RuntimeException("asset '$name' not found in latest release");
}

[$dest, $want] = targetPath();
if (is_file($dest) && filesize($dest) > 0) {
    fwrite(STDOUT, "[astra512-ffi] using existing $dest\n");
    exit(0);
}
try {
    [, $token] = latestRelease();
    downloadAsset($want, $dest, $token ?? '');
    fwrite(STDOUT, "[astra512-ffi] installed $want → $dest\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[astra512-ffi] WARN: ".$e->getMessage()."\n");
    fwrite(STDERR, "If needed, set ASTRA512_LIB to your local lib path.\n");
}
