<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptTermsRequest;
use App\Http\Responses\ApiResponse;
use App\Services\TermsService;
use Illuminate\Http\JsonResponse;

class TermsController extends Controller
{
    public function __construct(private TermsService $terms) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->terms->current());
    }

    public function accept(AcceptTermsRequest $request): JsonResponse
    {
        $acceptance = $this->terms->accept($request->user(), $request->input('version'), $request->ip(), $request->userAgent());

        return ApiResponse::success([
            'version' => $acceptance->version,
            'accepted_at' => $acceptance->accepted_at->toIso8601String(),
        ], 'Thank you. You can now load money, upload ads and book screens.');
    }
}
