<?php

use App\Http\Controllers\Api\V1\Admin\CreativeReviewController;
use App\Http\Controllers\Api\V1\Admin\DocumentReviewController;
use App\Http\Controllers\Api\V1\Admin\OrganisationAdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\CreativeController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\OrganisationController;
use App\Http\Controllers\Api\V1\OwnerScreenController;
use App\Http\Controllers\Api\V1\ScreenCatalogueController;
use App\Http\Controllers\Api\V1\TermsController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    // Sign-in. Per-IP and per-account throttling here, plus Nginx limits in front.
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('phone/code', [AuthController::class, 'phoneCode']);
        Route::post('phone/login', [AuthController::class, 'phoneLogin']);
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('sso', [AuthController::class, 'sso']);
    });

    // Public: the terms everyone must accept, and the screen catalogue.
    Route::get('legal/terms', [TermsController::class, 'show']);
    Route::get('screens', [ScreenCatalogueController::class, 'index']);
    Route::get('screens/{screen}', [ScreenCatalogueController::class, 'show'])->whereUuid('screen');
    Route::get('screens/{screen}/availability', [ScreenCatalogueController::class, 'availability'])->whereUuid('screen');

    // Ad and document files, behind short-lived signed links (no token in the URL).
    Route::middleware('signed:relative')->group(function () {
        Route::get('media/creatives/{creative}/{variant}', [MediaController::class, 'creative'])
            ->whereUuid('creative')->whereIn('variant', ['media', 'thumb'])->name('media.creative');
        Route::get('media/documents/{document}', [MediaController::class, 'document'])
            ->whereUuid('document')->name('media.document');
    });

    Route::middleware(['auth:api', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::middleware('throttle:auth')->group(function () {
            Route::post('auth/mfa/setup', [AuthController::class, 'mfaSetup']);
            Route::post('auth/mfa/enable', [AuthController::class, 'mfaEnable']);
            Route::post('auth/mfa/verify', [AuthController::class, 'mfaVerify']);
        });
        Route::post('auth/terms/accept', [TermsController::class, 'accept']);

        Route::get('me/documents', [DocumentController::class, 'index']);
        Route::post('me/documents', [DocumentController::class, 'store'])->middleware('terms');

        Route::get('orgs', [OrganisationController::class, 'index']);
        Route::post('orgs', [OrganisationController::class, 'store']);

        Route::prefix('orgs/{org}')->whereUuid('org')->group(function () {
            Route::get('/', [OrganisationController::class, 'show']);

            // Advertiser side: wallet, ads, bookings.
            Route::get('wallet', [WalletController::class, 'show']);
            Route::get('wallet/transactions', [WalletController::class, 'transactions']);
            Route::post('wallet/topup', [WalletController::class, 'topUp'])->middleware('terms');

            Route::get('creatives', [CreativeController::class, 'index']);
            Route::post('creatives', [CreativeController::class, 'store'])->middleware('terms');
            Route::get('creatives/{creative}', [CreativeController::class, 'show'])->whereUuid('creative');
            Route::delete('creatives/{creative}', [CreativeController::class, 'destroy'])->whereUuid('creative');

            Route::get('bookings', [BookingController::class, 'index']);
            Route::post('bookings', [BookingController::class, 'store'])->middleware('terms');
            Route::get('bookings/{booking}', [BookingController::class, 'show'])->whereUuid('booking');
            Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->whereUuid('booking');

            // Campaigns: optional grouping (not used by the booking flow).
            Route::get('campaigns', [CampaignController::class, 'index']);
            Route::post('campaigns', [CampaignController::class, 'store']);
            Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->whereUuid('campaign');

            // Owner side: changes need a two-factor-verified session.
            Route::get('screens', [OwnerScreenController::class, 'index']);
            Route::get('screens/{screen}', [OwnerScreenController::class, 'show'])->whereUuid('screen');
            Route::middleware('mfa')->group(function () {
                Route::post('screens', [OwnerScreenController::class, 'store']);
                Route::patch('screens/{screen}', [OwnerScreenController::class, 'update'])->whereUuid('screen');
            });
        });

        // Platform admin and the ad review team: two-factor always.
        Route::prefix('admin')->middleware(['role:admin,api', 'mfa'])->group(function () {
            Route::get('orgs', [OrganisationAdminController::class, 'index']);
            Route::post('orgs/{org}/verify', [OrganisationAdminController::class, 'verify'])->whereUuid('org');
            Route::post('orgs/{org}/suspend', [OrganisationAdminController::class, 'suspend'])->whereUuid('org');
            Route::post('orgs/{org}/reinstate', [OrganisationAdminController::class, 'reinstate'])->whereUuid('org');

            Route::get('creatives', [CreativeReviewController::class, 'index']);
            Route::post('creatives/{creative}/approve', [CreativeReviewController::class, 'approve'])->whereUuid('creative');
            Route::post('creatives/{creative}/reject', [CreativeReviewController::class, 'reject'])->whereUuid('creative');

            Route::get('documents', [DocumentReviewController::class, 'index']);
            Route::post('documents/{document}/approve', [DocumentReviewController::class, 'approve'])->whereUuid('document');
            Route::post('documents/{document}/reject', [DocumentReviewController::class, 'reject'])->whereUuid('document');
        });
    });
});
