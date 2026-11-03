<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CreativeStatus;
use App\Enums\RejectReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentDecisionRequest;
use App\Http\Requests\RejectCreativeRequest;
use App\Http\Requests\ReviewListRequest;
use App\Http\Resources\CreativeResource;
use App\Http\Responses\ApiResponse;
use App\Services\CreativeService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;

/** /admin/creatives — the review team's queue of ads. */
class CreativeReviewController extends Controller
{
    public function __construct(private CreativeService $creatives, private ReviewService $reviews) {}

    public function index(ReviewListRequest $request): JsonResponse
    {
        $status = $request->input('status', 'in_review');
        $page = $this->creatives->paginateForReview($status === 'all' ? null : CreativeStatus::tryFrom($status) ?? CreativeStatus::InReview);

        return ApiResponse::success(
            collect($page->items())->map(fn ($c) => (new CreativeResource($c))->reviewer())->values(),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'reasons' => collect(RejectReason::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])->values(),
            ],
        );
    }

    public function approve(DocumentDecisionRequest $request, string $creative): JsonResponse
    {
        $approved = $this->reviews->approve($request->user(), $creative, $request->input('note'));

        return ApiResponse::success((new CreativeResource($approved))->reviewer(), 'Ad approved. Its bookings are scheduled.');
    }

    public function reject(RejectCreativeRequest $request, string $creative): JsonResponse
    {
        $rejected = $this->reviews->reject($request->user(), $creative, RejectReason::from($request->input('reason')), $request->input('note'));

        return ApiResponse::success((new CreativeResource($rejected))->reviewer(), 'Ad refused. The advertiser sees your reason and gets unused money back.');
    }
}
