<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\InvoicePayos;
use App\Services\InvoiceQueries;
use App\Services\PayosGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicePayosController extends Controller
{
    public function show(Request $request, string $invoice, InvoiceQueries $invoices, InvoicePayos $payments, PayosGateway $gateway): JsonResponse
    {
        abort_unless($gateway->configured(), 404);
        $visible = $invoices->query(tenantUserId: $request->user('web')->id)->findOrFail($invoice);

        return response()->json(['data' => $payments->state($visible)]);
    }

    public function store(Request $request, string $invoice, InvoicePayos $payments): JsonResponse
    {
        return response()->json(['data' => $payments->start((int) $invoice, $request->user('web')->id)]);
    }
}
