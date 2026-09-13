<?php
declare(strict_types=1);

function cr_base64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function cr_request_origin(): string
{
    $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = $forwarded === 'https'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return rtrim((string)BASE_URL, '/');
    }

    return ($https ? 'https' : 'http') . '://' . $host;
}

/** @return array{publicKey: string, privateKey: string} */
function cr_vapid_keys(): array
{
    $path = appDataPath('vapid.json');
    if (is_file($path)) {
        $json = json_decode((string)file_get_contents($path), true);
        if (is_array($json) && !empty($json['publicKey']) && !empty($json['privateKey'])) {
            return [
                'publicKey' => (string)$json['publicKey'],
                'privateKey' => (string)$json['privateKey'],
            ];
        }
    }

    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    if ($key === false) {
        return ['publicKey' => '', 'privateKey' => ''];
    }

    $details = openssl_pkey_get_details($key);
    if ($details === false || empty($details['ec']['x']) || empty($details['ec']['y']) || empty($details['ec']['d'])) {
        return ['publicKey' => '', 'privateKey' => ''];
    }

    $x = str_pad((string)$details['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad((string)$details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $d = str_pad((string)$details['ec']['d'], 32, "\0", STR_PAD_LEFT);
    $out = [
        'publicKey' => cr_base64url_encode("\x04" . $x . $y),
        'privateKey' => cr_base64url_encode($d),
    ];
    @file_put_contents($path, json_encode($out), LOCK_EX);
    @chmod($path, 0600);

    return $out;
}
