<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CashFlowRequest;
use App\Http\Resources\CashFlowResource;
use App\Services\CashBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashFlowController extends Controller
{
    public function __construct(private readonly CashBook $book) {}

    public function index(CashFlowRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return CashFlowResource::collection($this->book->ledger($filters)->orderByDesc('occurred_at')->orderByDesc('source')->orderByDesc('source_id')
            ->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function summary(CashFlowRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->book->summary($request->validated())]);
    }
}
