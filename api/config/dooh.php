<?php

// Lab shortcuts (password login, codes on screen, fake plays) can never switch on in production,
// whatever the .env says.
$lab = env('APP_ENV') !== 'production';

return [
    'rentall' => [
        'base_url' => env('RENTALL_BASE_URL', 'https://rentallnepal.com'),
        'sso_client_secret' => env('RENTALL_SSO_CLIENT_SECRET'),
    ],

    'lab_local_login' => $lab && (bool) env('LAB_LOCAL_LOGIN', false),

    // Screen owner changes (screens, players, play order) need two-factor. Lab may turn it off.
    'owner_requires_mfa' => ! $lab || (bool) env('OWNER_REQUIRE_MFA', true),

    'manifest' => [
        // Base64 32-byte Ed25519 seed. Only the signer (scheduler) needs it; the API never does.
        'signing_key_path' => env('MANIFEST_SIGNING_KEY_PATH'),
        // The public half, handed to devices when they enrol.
        'public_key' => env('MANIFEST_SIGNING_PUBKEY'),
        // Re-sign an unchanged playlist when it has less than this left before it expires.
        'renew_before_seconds' => 12 * 3600,
    ],

    // Screen players: client certificates from the device CA, and the one-time enrolment code.
    'devices' => [
        'ca_cert_path' => env('DEVICE_CA_CERT_PATH', '/run/secrets/device_ca.crt'),
        'ca_key_path' => env('DEVICE_CA_KEY_PATH', '/run/secrets/device_ca.key'),
        'cert_days' => 365,
        'code_ttl_minutes' => 30,
    ],

    // Booking hours, play times and "today" are Nepal time, whatever the server clock says.
    'timezone' => env('DOOH_TIMEZONE', 'Asia/Kathmandu'),

    // Sign-in with a Nepali mobile number and a one-time SMS code.
    'otp' => [
        'ttl_seconds' => 300,
        'max_attempts' => 5,
        'resend_seconds' => 60,
        // Lab only: return the code in the API response instead of needing a real SMS.
        'expose_code' => $lab && (bool) env('OTP_EXPOSE_CODE', false) && (bool) env('LAB_LOCAL_LOGIN', false),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'), // log | (sparrow, aakash when added)
    ],

    'wallet' => [
        'min_topup' => 100,
        'max_topup' => 200000,
        // Lab: top-ups succeed at once. Real eSewa / Khalti callbacks replace this later.
        'gateways' => ['esewa', 'khalti', 'bank'],
    ],

    'creatives' => [
        'max_image_mb' => 10,
        'max_video_mb' => 200,
        'max_video_seconds' => 60,
        // Resolutions an advertiser can choose. The file must have the same shape (aspect ratio).
        'resolutions' => [
            ['width' => 1920, 'height' => 1080, 'label' => 'Full HD landscape'],
            ['width' => 1080, 'height' => 1920, 'label' => 'Full HD portrait'],
            ['width' => 3840, 'height' => 2160, 'label' => '4K landscape'],
            ['width' => 2160, 'height' => 3840, 'label' => '4K portrait'],
            ['width' => 1280, 'height' => 720, 'label' => 'HD landscape'],
            ['width' => 720, 'height' => 1280, 'label' => 'HD portrait'],
        ],
    ],

    // Lab: the scheduler fakes proof-of-play for live bookings until real players report it.
    'simulate_plays' => $lab && (bool) env('SIMULATE_PLAYS', false),
];
