<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminListRequest;
use App\Http\Resources\BookingResource;
use App\Http\Responses\ApiResponse;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /admin/bookings — every booking on every screen. */
class BookingAdminController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function index(AdminListRequest $request): JsonResponse
    {
        $page = $this->bookings->paginateForAdmin($request->input('status'), $request->input('search'));

        return ApiResponse::success(
            collect($page->items())->map(fn ($b) => (new BookingResource($b))->admin())->values(),
            meta: ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        );
    }

    public function cancel(Request $request, string $booking): JsonResponse
    {
        $b = $this->bookings->cancelByAdmin($request->user(), $booking);

        return ApiResponse::success((new BookingResource($b))->admin(), 'Booking cancelled. The advertiser was refunded for unplayed plays.');
    }
}
