<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Http\Responses\ApiResponse;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(private CampaignService $campaigns) {}

    public function index(Request $request, string $org): JsonResponse
    {
        return ApiResponse::success(CampaignResource::collection($this->campaigns->list($request->user(), $org)));
    }

    public function store(StoreCampaignRequest $request, string $org): JsonResponse
    {
        $campaign = $this->campaigns->create($request->user(), $org, $request->validated());

        return ApiResponse::success(new CampaignResource($campaign->refresh()), 'Campaign created.', 201);
    }

    public function show(Request $request, string $org, string $campaign): JsonResponse
    {
        return ApiResponse::success(new CampaignResource($this->campaigns->find($request->user(), $org, $campaign)));
    }
}
