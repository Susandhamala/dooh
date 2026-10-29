<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Responses\ApiResponse;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /orgs/{org}/campaigns/{campaign}/bookings */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request, string $org, string $campaign): JsonResponse
    {
        return ApiResponse::success(BookingResource::collection($this->bookings->list($request->user(), $org, $campaign)));
    }

    public function store(StoreBookingRequest $request, string $org, string $campaign): JsonResponse
    {
        $booking = $this->bookings->hold($request->user(), $org, $campaign, $request->validated());

        return ApiResponse::success(new BookingResource($booking->refresh()->load('screen')), 'Slot held. Upload your ad to continue.', 201);
    }

    public function cancel(Request $request, string $org, string $campaign, string $booking): JsonResponse
    {
        $cancelled = $this->bookings->cancel($request->user(), $org, $campaign, $booking);

        return ApiResponse::success(new BookingResource($cancelled->load('screen')), 'Booking cancelled.');
    }
}
