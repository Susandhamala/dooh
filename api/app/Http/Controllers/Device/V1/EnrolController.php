<?php

namespace App\Http\Controllers\Device\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrolDeviceRequest;
use App\Http\Responses\ApiResponse;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;

/** POST /device/v1/enrol: the one device route that works without a client certificate. */
class EnrolController extends Controller
{
    public function __invoke(EnrolDeviceRequest $request, DeviceService $devices): JsonResponse
    {
        $enrolled = $devices->enrol($request->validated('code'), $request->validated('csr'));

        return ApiResponse::success([
            'device_id' => $enrolled['device']->uuid,
            'screen_id' => $enrolled['device']->screen->uuid,
            'screen_name' => $enrolled['device']->screen->name,
            'certificate' => $enrolled['certificate'],
            'ca_certificate' => $enrolled['ca_certificate'],
            'signing_public_key' => $enrolled['signing_public_key'],
        ], 'Device enrolled.', 201);
    }
}
