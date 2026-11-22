<?php

namespace App\Http\Middleware;

use App\Services\DeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Device routes: the request must come through the mutual TLS server block, which sets
 * DEVICE_CERT_FINGERPRINT (a server variable, so no client header can set it). The device it
 * belongs to must be enrolled and not revoked. Controllers read it from the `device` attribute.
 */
class AuthenticateDevice
{
    public function __construct(private DeviceService $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->devices->authenticate(
            $request->server('DEVICE_CERT_FINGERPRINT'),
            $request->ip(),
            $request->header('X-Player-Version'),
        );
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
