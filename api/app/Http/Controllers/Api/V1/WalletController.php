<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TopUpRequest;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /orgs/{org}/wallet */
class WalletController extends Controller
{
    public function __construct(private WalletService $wallets) {}

    public function show(Request $request, string $org): JsonResponse
    {
        return ApiResponse::success(new WalletResource($this->wallets->show($request->user(), $org)));
    }

    public function transactions(Request $request, string $org): JsonResponse
    {
        $page = $this->wallets->transactions($request->user(), $org);

        return ApiResponse::success(WalletTransactionResource::collection($page->items()), meta: [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function topUp(TopUpRequest $request, string $org): JsonResponse
    {
        $entry = $this->wallets->topUp($request->user(), $org, (string) $request->integer('amount'), $request->input('gateway'));

        return ApiResponse::success([
            'transaction' => new WalletTransactionResource($entry),
            'wallet' => new WalletResource($this->wallets->show($request->user(), $org)),
        ], 'Money loaded.', 201);
    }
}
