<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\InvoicePreviewRequest;
use App\Http\Requests\Owner\IssueInvoiceRequest;
use App\Http\Requests\Owner\IssueOneTimeInvoiceRequest;
use App\Http\Requests\Owner\OneTimeInvoicePreviewRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Occupancy;
use App\Services\InvoiceBilling;
use App\Services\InvoiceQueries;
use Illuminate\Http\JsonResponse;

class InvoiceIssueController extends Controller
{
    public function __construct(private readonly InvoiceBilling $billing, private readonly InvoiceQueries $queries) {}

    public function preview(InvoicePreviewRequest $request, Occupancy $occupancy): JsonResponse
    {
        return response()->json(['data' => $this->billing->preview(
            $occupancy, $request->validated('billing_month'), $request->validated('service_ids'),
        )]);
    }

    public function previewOneTime(OneTimeInvoicePreviewRequest $request, Occupancy $occupancy): JsonResponse
    {
        return response()->json(['data' => $this->billing->previewOneTime($occupancy, $request->validated())]);
    }

    public function storeOneTime(IssueOneTimeInvoiceRequest $request, Occupancy $occupancy): JsonResponse
    {
        $result = $this->billing->issueOneTime($occupancy, $request->validated(), $request->user()->id);

        return $this->publication($result['invoice'], $result['replayed'] ? 200 : 201);
    }

    public function store(IssueInvoiceRequest $request, Occupancy $occupancy): JsonResponse
    {
        return $this->publication($this->billing->issue($occupancy, $request->validated(), $request->user()->id), 201);
    }

    private function publication(Invoice $invoice, int $status): JsonResponse
    {
        return response()->json([
            'message' => 'Đã phát hành hóa đơn. Quyền xem được cấp khi người đại diện có tài khoản đang hoạt động.',
            'data' => new InvoiceResource($this->queries->query([], null, true)->findOrFail($invoice->id)),
        ], $status);
    }
}
