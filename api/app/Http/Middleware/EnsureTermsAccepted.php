<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Services\TermsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uploading, booking and loading money need the current terms and content policy accepted. */
class EnsureTermsAccepted
{
    public function __construct(private TermsService $terms) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->terms->hasAccepted($request->user())) {
            return $next($request);
        }

        return ApiResponse::error('Please read and accept the terms and content policy first.', 403, [
            'terms' => [$this->terms->version()],
        ]);
    }
}
