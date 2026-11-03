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
        $message = $booking->status->value === 'scheduled'
            ? 'Booked. Your ad is approved and will play at the booked times.'
            : 'Booked. Your ad plays once the review team approves it.';

        return ApiResponse::success(new BookingResource($booking->refresh()->load(['screen', 'creative'])), $message, 201);
    }

    public function cancel(Request $request, string $org, string $booking): JsonResponse
    {
        $cancelled = $this->bookings->cancel($request->user(), $org, $booking);

        return ApiResponse::success(new BookingResource($cancelled->load(['screen', 'creative'])), 'Booking cancelled. Unused money is back in your wallet.');
    }
}
