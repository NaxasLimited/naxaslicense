<?php

namespace App\Services\Signing;

use RuntimeException;

class SigningKeyResolver
{
    public function resolve()
    {
        $path = config('license.private_key_path');
        if (! $path || ! str_starts_with($path, DIRECTORY_SEPARATOR) || is_link($path) || str_starts_with(realpath($path) ?: '', base_path()) || str_starts_with(realpath($path) ?: '', public_path()) || ! is_readable($path)) {
            throw new RuntimeException('Signing key is unavailable.');
        } $key = openssl_pkey_get_private(file_get_contents($path));
        $d = $key ? openssl_pkey_get_details($key) : false;
        if (! $d || $d['type'] !== OPENSSL_KEYTYPE_RSA || $d['bits'] < 3072) {
            throw new RuntimeException('Signing key is invalid.');
        }

        return $key;
    }
}
