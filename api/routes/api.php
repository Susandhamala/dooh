<?php

use App\Http\Controllers\Api\V1\Admin\OrganisationAdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrganisationController;
use App\Http\Controllers\Api\V1\OwnerScreenController;
use App\Http\Controllers\Api\V1\ScreenCatalogueController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    // Sign-in. Per-IP throttling here, plus Nginx limits in front.
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('sso', [AuthController::class, 'sso']);
    });

    // Public catalogue.
    Route::get('screens', [ScreenCatalogueController::class, 'index']);
    Route::get('screens/{screen}', [ScreenCatalogueController::class, 'show'])->whereUuid('screen');
    Route::get('screens/{screen}/availability', [ScreenCatalogueController::class, 'availability'])->whereUuid('screen');

    Route::middleware(['auth:api', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::middleware('throttle:auth')->group(function () {
            Route::post('auth/mfa/setup', [AuthController::class, 'mfaSetup']);
            Route::post('auth/mfa/enable', [AuthController::class, 'mfaEnable']);
            Route::post('auth/mfa/verify', [AuthController::class, 'mfaVerify']);
        });

        Route::get('orgs', [OrganisationController::class, 'index']);
        Route::post('orgs', [OrganisationController::class, 'store']);

        Route::prefix('orgs/{org}')->whereUuid('org')->group(function () {
            Route::get('/', [OrganisationController::class, 'show']);

            // Advertiser side.
            Route::get('campaigns', [CampaignController::class, 'index']);
            Route::post('campaigns', [CampaignController::class, 'store']);
            Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->whereUuid('campaign');
            Route::get('campaigns/{campaign}/bookings', [BookingController::class, 'index'])->whereUuid('campaign');
            Route::post('campaigns/{campaign}/bookings', [BookingController::class, 'store'])->whereUuid('campaign');
            Route::post('campaigns/{campaign}/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
                ->whereUuid(['campaign', 'booking']);

            // Owner side: changes need a two-factor-verified session.
            Route::get('screens', [OwnerScreenController::class, 'index']);
            Route::get('screens/{screen}', [OwnerScreenController::class, 'show'])->whereUuid('screen');
            Route::middleware('mfa')->group(function () {
                Route::post('screens', [OwnerScreenController::class, 'store']);
                Route::patch('screens/{screen}', [OwnerScreenController::class, 'update'])->whereUuid('screen');
            });
        });

        Route::prefix('admin')->middleware(['role:admin,api', 'mfa'])->group(function () {
            Route::get('orgs', [OrganisationAdminController::class, 'index']);
            Route::post('orgs/{org}/verify', [OrganisationAdminController::class, 'verify'])->whereUuid('org');
            Route::post('orgs/{org}/suspend', [OrganisationAdminController::class, 'suspend'])->whereUuid('org');
            Route::post('orgs/{org}/reinstate', [OrganisationAdminController::class, 'reinstate'])->whereUuid('org');
        });
    });
});
