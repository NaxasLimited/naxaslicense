<?php

return [
    'portal_url' => env('LICENSE_PORTAL_URL', 'https://licenses.naxasltd.com'),
    'allow_local_http' => (bool) env('LICENSE_ALLOW_LOCAL_HTTP', false),
    'trusted_local_hosts' => array_values(array_filter(array_map('trim', explode(',', env(
        'LICENSE_TRUSTED_LOCAL_HOSTS',
        '127.0.0.1,localhost,::1,naxas-license-portal.test'
    ))))),
    'public_key_path' => env('LICENSE_PUBLIC_KEY_PATH'),
    'product' => 'buildora-cms',
    'license_type' => 'single_site',
    'timeout' => (int) env('LICENSE_PORTAL_TIMEOUT_SECONDS', 10),
    'max_response_bytes' => (int) env('LICENSE_MAX_RESPONSE_BYTES', 131072),
];
