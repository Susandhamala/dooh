<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecideBookingRequest;
use App\Http\Requests\MoveBookingRequest;
use App\Http\Requests\ScheduleRequest;
use App\Http\Responses\ApiResponse;
use App\Services\OwnerScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A screen owner's dashboard, the booked slots of a screen, and changing the play order. */
class OwnerScheduleController extends Controller
{
    public function __construct(private OwnerScheduleService $owner) {}

    public function overview(Request $request, string $org): JsonResponse
    {
        return ApiResponse::success($this->owner->overview($request->user(), $org));
    }

    public function schedule(ScheduleRequest $request, string $org, string $screen): JsonResponse
    {
        return ApiResponse::success($this->owner->schedule($request->user(), $org, $screen, $this->day($request->validated('date'))));
    }

    public function move(MoveBookingRequest $request, string $org, string $screen, string $booking): JsonResponse
    {
        $this->owner->move(
            $request->user(), $org, $screen, $booking,
            $request->validated('direction'), $this->day($request->validated('date')), (int) $request->validated('hour'),
        );

        return ApiResponse::success($this->owner->schedule($request->user(), $org, $screen, $this->day($request->validated('date'))), 'Play order changed.');
    }

    /** One click: the ad plays on the screen at its booked times. */
    public function approve(DecideBookingRequest $request, string $org, string $screen, string $booking): JsonResponse
    {
        $this->owner->decide($request->user(), $org, $screen, $booking, true, $request->validated('note'));

        return ApiResponse::success($this->owner->schedule($request->user(), $org, $screen, $this->day($request->validated('date'))), 'Approved. It plays at its booked times.');
    }

    /** Not on this screen: the advertiser gets their money back. */
    public function reject(DecideBookingRequest $request, string $org, string $screen, string $booking): JsonResponse
    {
        $this->owner->decide($request->user(), $org, $screen, $booking, false, $request->validated('note'));

        return ApiResponse::success($this->owner->schedule($request->user(), $org, $screen, $this->day($request->validated('date'))), 'Rejected. The advertiser was refunded.');
    }

    private function day(?string $date): CarbonImmutable
    {
        $tz = config('dooh.timezone');

        return $date ? CarbonImmutable::createFromFormat('Y-m-d', $date, $tz)->startOfDay() : CarbonImmutable::now($tz)->startOfDay();
    }
}
