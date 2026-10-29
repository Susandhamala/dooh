<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owner and admin actions need a token that passed a TOTP check (claim mfa=true).
 * Users without MFA are told to enrol; users with MFA are told to verify.
 */
class RequireMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('api')->payload()->get('mfa') === true) {
            return $next($request);
        }

        $enrolled = $request->user()->hasMfa();

        return ApiResponse::error(
            $enrolled ? 'Two-factor verification required.' : 'Set up two-factor authentication to continue.',
            403,
            ['mfa' => [$enrolled ? 'verify' : 'setup']],
        );
    }
}
