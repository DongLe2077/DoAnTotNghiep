<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\InvoicePreviewRequest;
use App\Http\Requests\Owner\IssueInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Occupancy;
use App\Services\InvoiceBilling;
use App\Services\InvoiceQueries;
use Illuminate\Http\JsonResponse;

class InvoiceIssueController extends Controller
{
    public function __construct(private readonly InvoiceBilling $billing, private readonly InvoiceQueries $queries) {}

    public function preview(InvoicePreviewRequest $request, Occupancy $occupancy): JsonResponse
    {
        return response()->json(['data' => $this->billing->preview($occupancy, $request->validated('billing_month'))]);
    }

    public function store(IssueInvoiceRequest $request, Occupancy $occupancy): JsonResponse
    {
        $invoice = $this->billing->issue($occupancy, $request->validated(), $request->user()->id);

        return response()->json([
            'message' => 'Đã phát hành hóa đơn. Quyền xem được cấp khi người đại diện có tài khoản đang hoạt động.',
            'data' => new InvoiceResource($this->queries->query([], null, true)->findOrFail($invoice->id)),
        ], 201);
    }
}
