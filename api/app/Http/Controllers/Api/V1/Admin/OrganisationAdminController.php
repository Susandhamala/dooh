<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganisationResource;
use App\Http\Responses\ApiResponse;
use App\Services\OrganisationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganisationAdminController extends Controller
{
    public function __construct(private OrganisationService $orgs) {}

    public function index(Request $request): JsonResponse
    {
        $verified = $request->has('verified') ? $request->boolean('verified') : null;
        $page = $this->orgs->paginateForAdmin($verified);

        return ApiResponse::success(OrganisationResource::collection($page->items()), meta: [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function verify(string $org): JsonResponse
    {
        return ApiResponse::success(new OrganisationResource($this->orgs->verify($org)), 'Organisation verified.');
    }

    public function suspend(string $org): JsonResponse
    {
        return ApiResponse::success(new OrganisationResource($this->orgs->setSuspended($org, true)), 'Organisation suspended.');
    }

    public function reinstate(string $org): JsonResponse
    {
        return ApiResponse::success(new OrganisationResource($this->orgs->setSuspended($org, false)), 'Organisation reinstated.');
    }
}
