<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Responses\ApiResponse;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /orgs/{org}/bookings */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(Request $request, string $org): JsonResponse
    {
        return ApiResponse::success(BookingResource::collection($this->bookings->list($request->user(), $org)));
    }

    public function show(Request $request, string $org, string $booking): JsonResponse
    {
        return ApiResponse::success(new BookingResource($this->bookings->find($request->user(), $org, $booking)));
    }

    public function store(StoreBookingRequest $request, string $org): JsonResponse
    {
        $booking = $this->bookings->book($request->user(), $org, $request->validated());

        return ApiResponse::success(
            new BookingResource($booking->refresh()->load(['screen', 'creative'])),
            'Paid and booked. The slot is yours and your ad plays at the booked times.',
            201,
        );
    }

    public function cancel(Request $request, string $org, string $booking): JsonResponse
    {
        $cancelled = $this->bookings->cancel($request->user(), $org, $booking);

        return ApiResponse::success(new BookingResource($cancelled->load(['screen', 'creative'])), 'Booking cancelled. Unplayed plays are refunded to your wallet.');
    }
}
