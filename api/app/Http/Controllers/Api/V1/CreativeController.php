<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCreativeRequest;
use App\Http\Resources\CreativeResource;
use App\Http\Responses\ApiResponse;
use App\Services\CreativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /orgs/{org}/creatives — the advertiser's ads. */
class CreativeController extends Controller
{
    public function __construct(private CreativeService $creatives) {}

    public function index(Request $request, string $org): JsonResponse
    {
        return ApiResponse::success(CreativeResource::collection($this->creatives->list($request->user(), $org)), meta: [
            'resolutions' => config('dooh.creatives.resolutions'),
            'max_image_mb' => config('dooh.creatives.max_image_mb'),
            'max_video_mb' => config('dooh.creatives.max_video_mb'),
            'max_video_seconds' => config('dooh.creatives.max_video_seconds'),
            'declaration' => config('terms.declaration'),
        ]);
    }

    public function show(Request $request, string $org, string $creative): JsonResponse
    {
        return ApiResponse::success(new CreativeResource($this->creatives->find($request->user(), $org, $creative)));
    }

    public function store(StoreCreativeRequest $request, string $org): JsonResponse
    {
        $creative = $this->creatives->upload(
            $request->user(), $org, $request->safe()->only(['name', 'category', 'resolution']), $request->file('file'),
        );

        return ApiResponse::success(new CreativeResource($creative->refresh()), 'Uploaded. We are checking the file now.', 201);
    }

    public function destroy(Request $request, string $org, string $creative): JsonResponse
    {
        $this->creatives->delete($request->user(), $org, $creative);

        return ApiResponse::success(null, 'Ad deleted.');
    }
}
