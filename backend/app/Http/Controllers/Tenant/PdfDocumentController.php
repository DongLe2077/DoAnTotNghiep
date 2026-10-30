<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\PdfDocuments;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PdfDocumentController extends Controller
{
    public function __construct(private readonly PdfDocuments $documents) {}

    public function invoice(Request $request, string $invoice): Response
    {
        return $this->documents->invoice((int) $invoice, $request->user('web')->id);
    }

    public function receipt(Request $request, string $invoice, string $payment): Response
    {
        return $this->documents->receipt((int) $invoice, (int) $payment, $request->user('web')->id);
    }
}
