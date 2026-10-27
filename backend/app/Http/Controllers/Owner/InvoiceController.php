<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ChangeInvoiceRequest;
use App\Http\Requests\Owner\GrantInvoiceAccessRequest;
use App\Http\Requests\Owner\InvoiceIndexRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceAccessManager;
use App\Services\InvoiceControls;
use App\Services\InvoiceQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceQueries $queries, private readonly InvoiceAccessManager $accessManager) {}

    public function index(InvoiceIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return InvoiceResource::collection($this->queries->query($filters)->orderByDesc('invoices.billing_month')->orderByDesc('invoices.id')
            ->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return $this->resource($invoice);
    }

    public function change(ChangeInvoiceRequest $request, Invoice $invoice, InvoiceControls $controls): JsonResponse
    {
        $result = $controls->change($invoice, $request->validated(), $request->user('web')->id);

        return response()->json(['replayed' => $result['replayed'], 'data' => $this->resource($result['invoice'])]);
    }

    public function access(GrantInvoiceAccessRequest $request, Invoice $invoice): JsonResponse
    {
        $result = $this->accessManager->grant($invoice, $request->validated(), $request->user('web')->id);

        return response()->json(['message' => 'Đã cấp quyền xem hóa đơn.', 'data' => $this->resource($result)]);
    }

    public function revokeAccess(Invoice $invoice): JsonResponse
    {
        $result = $this->accessManager->revoke($invoice);

        return response()->json(['message' => 'Đã thu hồi quyền xem hóa đơn.', 'data' => $this->resource($result)]);
    }

    private function resource(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->queries->query(detail: true)->findOrFail($invoice->id));
    }
}
