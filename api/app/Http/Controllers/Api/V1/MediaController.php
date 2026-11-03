<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CreativeService;
use App\Services\DocumentService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Files behind short-lived signed links (the `signed` middleware checks them), so <img> and <video>
 * can load them without a token in the URL. Video supports range requests for seeking.
 */
class MediaController extends Controller
{
    public function creative(CreativeService $creatives, string $creative, string $variant): BinaryFileResponse
    {
        $file = $creatives->media($creative, $variant);

        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, max-age=1800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function document(DocumentService $documents, string $document): Response
    {
        $file = $documents->file($document) ?? abort(404);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
