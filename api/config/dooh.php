<?php

return [
    'rentall' => [
        'base_url' => env('RENTALL_BASE_URL', 'https://rentallnepal.com'),
        'sso_client_secret' => env('RENTALL_SSO_CLIENT_SECRET'),
    ],

    'lab_local_login' => (bool) env('LAB_LOCAL_LOGIN', false),

    'manifest' => [
        'signing_key_path' => env('MANIFEST_SIGNING_KEY_PATH'),
    ],
];
