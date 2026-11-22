<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminListRequest;
use App\Http\Resources\AdminUserResource;
use App\Http\Responses\ApiResponse;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /admin/users — everyone who signed up, their verification, wallet and bookings. */
class UserAdminController extends Controller
{
    public function __construct(private AdminService $admin) {}

    public function index(AdminListRequest $request): JsonResponse
    {
        $page = $this->admin->users($request->input('search'), $request->input('status'));

        return ApiResponse::success(AdminUserResource::collection($page->items()), meta: [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function suspend(Request $request, string $user): JsonResponse
    {
        return ApiResponse::success(new AdminUserResource($this->admin->setSuspended($request->user(), $user, true)), 'User suspended. They are signed out everywhere.');
    }

    public function reinstate(Request $request, string $user): JsonResponse
    {
        return ApiResponse::success(new AdminUserResource($this->admin->setSuspended($request->user(), $user, false)), 'User reinstated.');
    }
}
