<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScreenRequest;
use App\Http\Resources\ScreenResource;
use App\Http\Responses\ApiResponse;
use App\Services\ScreenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A screen owner's own screens: /orgs/{org}/screens. */
class OwnerScreenController extends Controller
{
    public function __construct(private ScreenService $screens) {}

    public function index(Request $request, string $org): JsonResponse
    {
        return ApiResponse::success(ScreenResource::collection($this->screens->forOwner($request->user(), $org)));
    }

    public function store(ScreenRequest $request, string $org): JsonResponse
    {
        $screen = $this->screens->create($request->user(), $org, $request->validated());

        return ApiResponse::success(new ScreenResource($screen->refresh()->load('contentRules')), 'Screen created.', 201);
    }

    public function show(Request $request, string $org, string $screen): JsonResponse
    {
        return ApiResponse::success(new ScreenResource($this->screens->ownerScreen($request->user(), $org, $screen)));
    }

    public function update(ScreenRequest $request, string $org, string $screen): JsonResponse
    {
        $updated = $this->screens->update($request->user(), $org, $screen, $request->validated());

        return ApiResponse::success(new ScreenResource($updated->refresh()->load('contentRules')), 'Screen updated.');
    }
}
