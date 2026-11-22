<?php

use App\Http\Controllers\Device\V1\EnrolController;
use App\Http\Controllers\Device\V1\ManifestController;
use Illuminate\Support\Facades\Route;

// Screen players, under /device. Nginx serves these only on the mutual TLS port (8443).

Route::prefix('v1')->group(function () {
    // A new device has no certificate yet: it proves itself with the owner's one-time code.
    Route::post('enrol', EnrolController::class)->middleware('throttle:device-enrol');

    Route::middleware('device')->group(function () {
        Route::get('manifest', [ManifestController::class, 'show']);
        Route::get('media/{creative}', [ManifestController::class, 'media'])->whereUuid('creative');
    });
});
