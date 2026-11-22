<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\BookingResource;
use App\Http\Responses\ApiResponse;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

/** /admin/overview — the numbers and to-dos on the admin dashboard. */
class DashboardController extends Controller
{
    public function __construct(private AdminService $admin) {}

    public function __invoke(): JsonResponse
    {
        $data = $this->admin->overview();
        $data['recent_bookings'] = $data['recent_bookings']->map(fn ($b) => (new BookingResource($b))->admin())->values();
        $data['recent_users'] = AdminUserResource::collection($data['recent_users']);

        return ApiResponse::success($data);
    }
}
