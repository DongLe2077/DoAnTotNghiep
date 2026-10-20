<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PaymentIndexRequest;
use App\Http\Requests\Owner\RecordPaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoicePayments;
use App\Services\InvoiceQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(private readonly InvoicePayments $payments, private readonly InvoiceQueries $invoices) {}

    public function index(PaymentIndexRequest $request): AnonymousResourceCollection
    {
        return $this->collection($request->validated());
    }

    public function forInvoice(PaymentIndexRequest $request, Invoice $invoice): AnonymousResourceCollection
    {
        return $this->collection(array_merge($request->validated(), ['invoice_id' => $invoice->id]));
    }

    private function collection(array $filters): AnonymousResourceCollection
    {
        return PaymentResource::collection($this->payments->query($filters)->orderByDesc('paid_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function show(Payment $payment): PaymentResource
    {
        return new PaymentResource($this->payments->query()->findOrFail($payment->id));
    }

    public function store(RecordPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $result = $this->payments->record($invoice, $request->validated(), $request->user('web')->id);

        return response()->json([
            'message' => $result['replayed'] ? 'Yêu cầu đã được ghi nhận trước đó; trả lại lần thu đã lưu.' : 'Đã ghi nhận thu tiền hóa đơn.',
            'replayed' => $result['replayed'],
            'data' => new PaymentResource($this->payments->query()->findOrFail($result['payment']->id)),
            'invoice' => new InvoiceResource($this->invoices->query(detail: true)->findOrFail($invoice->id)),
        ], $result['replayed'] ? 200 : 201);
    }
}
