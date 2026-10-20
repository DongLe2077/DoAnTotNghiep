<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\InvoiceIndexRequest;
use App\Http\Resources\InvoiceResource;
use App\Services\InvoiceQueries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceQueries $queries) {}

    public function index(InvoiceIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return InvoiceResource::collection($this->queries->query($filters, $request->user('web')->id)
            ->orderByDesc('invoices.billing_month')->orderByDesc('invoices.id')->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function show(Request $request, string $invoice): InvoiceResource
    {
        return new InvoiceResource($this->queries->query(tenantUserId: $request->user('web')->id, detail: true)->findOrFail($invoice));
    }
}
