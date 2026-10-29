<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Requests\ScreenCatalogueRequest;
use App\Http\Resources\ScreenResource;
use App\Http\Responses\ApiResponse;
use App\Services\ScreenService;
use Illuminate\Http\JsonResponse;

/** Public screen catalogue that advertisers browse. */
class ScreenCatalogueController extends Controller
{
    public function __construct(private ScreenService $screens) {}

    public function index(ScreenCatalogueRequest $request): JsonResponse
    {
        $page = $this->screens->catalogue($request->filters());

        return ApiResponse::success(ScreenResource::collection($page->items()), meta: [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function show(string $screen): JsonResponse
    {
        return ApiResponse::success(new ScreenResource($this->screens->publicScreen($screen)));
    }

    public function availability(AvailabilityRequest $request, string $screen): JsonResponse
    {
        return ApiResponse::success($this->screens->availability(
            $screen,
            $request->date('from')->toImmutable(),
            $request->date('to')->toImmutable(),
        ));
    }
}
