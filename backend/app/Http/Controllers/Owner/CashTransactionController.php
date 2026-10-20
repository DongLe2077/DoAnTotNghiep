<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CashTransactionIndexRequest;
use App\Http\Requests\Owner\DeleteCashTransactionRequest;
use App\Http\Requests\Owner\RevertCashTransactionRequest;
use App\Http\Requests\Owner\SaveCashTransactionRequest;
use App\Http\Resources\CashTransactionHistoryResource;
use App\Http\Resources\CashTransactionResource;
use App\Models\CashTransaction;
use App\Services\CashBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CashTransactionController extends Controller
{
    public function __construct(private readonly CashBook $book) {}

    public function index(CashTransactionIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return CashTransactionResource::collection($this->book->query($filters)->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function store(SaveCashTransactionRequest $request): JsonResponse
    {
        return response()->json(['message' => 'Đã ghi nhận khoản thu chi.',
            'data' => new CashTransactionResource($this->book->create($request->validated(), $request->user('web')->id))], 201);
    }

    public function show(CashTransaction $cashTransaction): CashTransactionResource
    {
        return new CashTransactionResource($cashTransaction);
    }

    public function update(SaveCashTransactionRequest $request, CashTransaction $cashTransaction): JsonResponse
    {
        return response()->json(['message' => 'Đã cập nhật khoản thu chi.',
            'data' => new CashTransactionResource($this->book->update($cashTransaction, $request->validated(), $request->user('web')->id))]);
    }

    public function destroy(DeleteCashTransactionRequest $request, CashTransaction $cashTransaction): Response
    {
        $this->book->delete($cashTransaction, $request->validated('version_token'), $request->user('web')->id, $request->validated('reason'));

        return response()->noContent();
    }

    public function history(CashTransactionIndexRequest $request, string $cashTransaction): AnonymousResourceCollection
    {
        $entry = CashTransaction::withTrashed()->findOrFail($cashTransaction);

        return CashTransactionHistoryResource::collection($entry->histories()->with('performedBy')->orderByDesc('revision')
            ->paginate($request->validated('per_page', 15))->withQueryString())
            ->additional(['current' => new CashTransactionResource($entry)]);
    }

    public function revert(RevertCashTransactionRequest $request, string $cashTransaction): JsonResponse
    {
        return response()->json(['message' => 'Đã hoàn tác và ghi lại nhật ký.',
            'data' => new CashTransactionResource($this->book->revert($cashTransaction, $request->validated(), $request->user('web')->id))]);
    }
}
