<?php

namespace App\Http\Controllers\Device\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\ManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** What the authenticated device (set by the `device` middleware) should play, and the ad files. */
class ManifestController extends Controller
{
    public function __construct(private ManifestService $manifests) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->manifests->forDevice($request->attributes->get('device')));
    }

    public function media(Request $request, string $creative): BinaryFileResponse
    {
        $file = $this->manifests->media($request->attributes->get('device'), $creative);

        // The player checks the SHA-256 from the signed manifest before it keeps the file.
        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
