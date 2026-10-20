<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ReceivableRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ReceivableGroupResource;
use App\Services\InvoiceReports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReceivableController extends Controller
{
    public function __construct(private readonly InvoiceReports $reports) {}

    public function index(ReceivableRequest $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection($this->reports->outstanding($request->validated())
            ->orderBy('invoices.billing_month')->orderBy('invoices.id')
            ->paginate($request->validated('per_page', 15))->withQueryString());
    }

    public function summary(ReceivableRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->summary($request->validated())]);
    }

    public function groups(ReceivableRequest $request): AnonymousResourceCollection
    {
        return ReceivableGroupResource::collection($this->reports->groups($request->validated())
            ->orderByDesc('outstanding_amount')->orderBy('group_id')
            ->paginate($request->validated('per_page', 15))->withQueryString());
    }
}
