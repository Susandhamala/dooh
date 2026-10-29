<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganisationRequest;
use App\Http\Resources\OrganisationResource;
use App\Http\Responses\ApiResponse;
use App\Services\OrganisationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganisationController extends Controller
{
    public function __construct(private OrganisationService $orgs) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(OrganisationResource::collection($this->orgs->forUser($request->user())));
    }

    public function store(StoreOrganisationRequest $request): JsonResponse
    {
        $org = $this->orgs->create($request->user(), $request->validated());

        return ApiResponse::success(new OrganisationResource($org->refresh()), 'Organisation created.', 201);
    }

    public function show(Request $request, string $org): JsonResponse
    {
        $membership = $this->orgs->membership($request->user(), $org);

        return ApiResponse::success(new OrganisationResource($membership->organisation), meta: ['my_role' => $membership->role->value]);
    }
}
