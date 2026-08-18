#!/usr/bin/env sh
set -eu

curl --fail --silent --show-error http://127.0.0.1/health >/dev/null
php artisan migrate:status >/dev/null
php -r '
$path = getenv("LICENSE_SIGNING_PRIVATE_KEY_PATH");
if (! is_string($path) || $path === "" || ! is_readable($path)) {
    exit(1);
}
$key = openssl_pkey_get_private(file_get_contents($path));
if ($key === false) {
    exit(1);
}
$details = openssl_pkey_get_details($key);
exit(($details["type"] ?? null) === OPENSSL_KEYTYPE_RSA && ($details["bits"] ?? 0) >= 3072 ? 0 : 1);
'

