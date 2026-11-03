<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentDecisionRequest;
use App\Http\Requests\ReviewListRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Responses\ApiResponse;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;

/** /admin/documents — identity documents waiting for a decision. */
class DocumentReviewController extends Controller
{
    public function __construct(private DocumentService $documents) {}

    public function index(ReviewListRequest $request): JsonResponse
    {
        $status = $request->input('status', 'pending');
        $page = $this->documents->paginateForReview($status === 'all' ? null : ReviewStatus::tryFrom($status) ?? ReviewStatus::Pending);

        return ApiResponse::success(
            collect($page->items())->map(fn ($d) => (new DocumentResource($d))->reviewer())->values(),
            meta: ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        );
    }

    public function approve(DocumentDecisionRequest $request, string $document): JsonResponse
    {
        $doc = $this->documents->decide($request->user(), $document, true, $request->input('note'));

        return ApiResponse::success((new DocumentResource($doc))->reviewer(), 'Identity verified. The file has been deleted.');
    }

    public function reject(DocumentDecisionRequest $request, string $document): JsonResponse
    {
        $doc = $this->documents->decide($request->user(), $document, false, $request->input('note'));

        return ApiResponse::success((new DocumentResource($doc))->reviewer(), 'Document refused. The file has been deleted.');
    }
}
