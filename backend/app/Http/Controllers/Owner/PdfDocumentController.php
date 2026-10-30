<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PdfDocuments;
use Symfony\Component\HttpFoundation\Response;

class PdfDocumentController extends Controller
{
    public function __construct(private readonly PdfDocuments $documents) {}

    public function invoice(string $invoice): Response
    {
        return $this->documents->invoice((int) $invoice);
    }

    public function receipt(Payment $payment): Response
    {
        return $this->documents->receipt($payment->invoice_id, $payment->id);
    }
}
