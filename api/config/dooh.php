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

    // Booking hours, play times and "today" are Nepal time, whatever the server clock says.
    'timezone' => env('DOOH_TIMEZONE', 'Asia/Kathmandu'),

    // Sign-in with a Nepali mobile number and a one-time SMS code.
    'otp' => [
        'ttl_seconds' => 300,
        'max_attempts' => 5,
        'resend_seconds' => 60,
        // Lab only: return the code in the API response instead of needing a real SMS.
        'expose_code' => (bool) env('OTP_EXPOSE_CODE', false) && (bool) env('LAB_LOCAL_LOGIN', false),
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
    'simulate_plays' => (bool) env('SIMULATE_PLAYS', false),
];
