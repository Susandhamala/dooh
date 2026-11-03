<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Responses\ApiResponse;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /me/documents — the signed-in user's own identity documents. */
class DocumentController extends Controller
{
    public function __construct(private DocumentService $documents) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(DocumentResource::collection($this->documents->forUser($request->user())), meta: [
            'status' => $this->documents->status($request->user()),
        ]);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $doc = $this->documents->submit($request->user(), $request->safe()->only(['type', 'number']), $request->file('file'));

        return ApiResponse::success(new DocumentResource($doc), 'Document received. We usually check it within a day.', 201);
    }
}
