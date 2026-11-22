<?php

namespace App\Http\Middleware;

use App\Enums\ReviewStatus;
use App\Http\Responses\ApiResponse;
use App\Services\DocumentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every user uploads an identity document before loading money or uploading ads. Booking needs it
 * approved; that check is in BookingService.
 */
class EnsureIdentitySubmitted
{
    public function __construct(private DocumentService $documents) {}

    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->documents->status($request->user());
        if (in_array($status, [ReviewStatus::Pending->value, ReviewStatus::Approved->value], true)) {
            return $next($request);
        }

        return ApiResponse::error(
            $status === ReviewStatus::Rejected->value
                ? 'Your identity document was refused. Upload a clear one to continue.'
                : 'Upload your identity document first.',
            403,
            ['document' => ['required']],
        );
    }
}
