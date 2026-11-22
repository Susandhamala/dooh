<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Http\Responses\ApiResponse;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The player connected to an owner's screen: /orgs/{org}/screens/{screen}/device. */
class ScreenDeviceController extends Controller
{
    public function __construct(private DeviceService $devices) {}

    public function show(Request $request, string $org, string $screen): JsonResponse
    {
        $device = $this->devices->forScreen($request->user(), $org, $screen);

        return ApiResponse::success($device ? new DeviceResource($device) : null);
    }

    public function code(Request $request, string $org, string $screen): JsonResponse
    {
        $issued = $this->devices->issueCode($request->user(), $org, $screen);

        return ApiResponse::success(
            ['code' => $issued['code'], 'expires_at' => $issued['expires_at']->toIso8601String()],
            'Type this code on the screen device. It works once.',
            201,
        );
    }

    public function revoke(Request $request, string $org, string $screen): JsonResponse
    {
        $this->devices->revoke($request->user(), $org, $screen);

        return ApiResponse::success(null, 'Device disconnected.');
    }
}
