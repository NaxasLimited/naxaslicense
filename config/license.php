<?php

return [
    'issuer' => env('LICENSE_ISSUER', 'Naxas Limited'),
    'private_key_path' => env('LICENSE_SIGNING_PRIVATE_KEY_PATH'),
    'key_id' => env('LICENSE_SIGNING_KEY_ID'),
    'request_ttl' => (int) env('LICENSE_REQUEST_TTL_MINUTES', 1440),
    'create_limit' => (int) env('LICENSE_CREATE_RATE_LIMIT', 5),
    'status_limit' => (int) env('LICENSE_STATUS_RATE_LIMIT', 20),
    'portal_limit' => (int) env('PORTAL_SUBMIT_RATE_LIMIT', 10),
    'delivery_window_hours' => (int) env('LICENSE_DELIVERY_WINDOW_HOURS', 48),
    'allow_local_http' => (bool) env('LICENSE_ALLOW_LOCAL_HTTP', false),
    'local_http_hosts' => array_values(array_filter(array_map('trim', explode(',', env(
        'LICENSE_LOCAL_HTTP_HOSTS',
        '127.0.0.1,localhost,::1,naxas-license-portal.test'
    ))))),
];
