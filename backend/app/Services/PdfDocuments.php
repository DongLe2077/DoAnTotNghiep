<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PdfDocuments
{
    public function invoice(int $invoiceId, ?int $tenantUserId = null): Response
    {
        return $this->response($this->snapshot($invoiceId, null, $tenantUserId), 'hoa-don-'.$invoiceId.'.pdf');
    }

    public function receipt(int $invoiceId, int $paymentId, ?int $tenantUserId = null): Response
    {
        return $this->response($this->snapshot($invoiceId, $paymentId, $tenantUserId), 'phieu-thu-'.$paymentId.'.pdf');
    }

    /** Build a whitelisted, consistent read snapshot before rendering outside the transaction. */
    public function snapshot(int $invoiceId, ?int $paymentId = null, ?int $tenantUserId = null): array
    {
        return DB::transaction(function () use ($invoiceId, $paymentId, $tenantUserId) {
            $invoice = app(InvoiceQueries::class)->query(tenantUserId: $tenantUserId, detail: true)->findOrFail($invoiceId);
            $profile = app(RentalPropertyProfile::class)->current();
            $house = $profile === null ? null : array_intersect_key($profile->toArray(), array_flip([
                'name', 'address', 'contact_name', 'phone', 'email', 'bank_name', 'bank_account_number', 'bank_account_name', 'notes',
            ]));
            $payments = $invoice->payments->map(fn (Payment $payment) => $this->payment($payment))->all();
            $receipt = $paymentId === null ? null : collect($payments)->firstWhere('id', $paymentId);
            abort_if($paymentId !== null && $receipt === null, 404);

            return [
                'kind' => $paymentId === null ? 'invoice' : 'receipt',
                'profile' => $house,
                'exported_at' => now()->format('d/m/Y H:i:s'),
                'invoice' => $this->invoiceData($invoice),
                'payments' => $payments,
                'receipt' => $receipt,
            ];
        });
    }

    public function html(array $snapshot): string
    {
        return view('pdf.document', $snapshot)->render();
    }

    public function bytes(array $snapshot): string
    {
        $runtime = storage_path('framework/cache/pdf');
        if (! is_dir($runtime) && ! mkdir($runtime, 0700, true) && ! is_dir($runtime)) {
            throw new \RuntimeException('Unable to prepare private PDF runtime.');
        }
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([resource_path('views/pdf'), base_path('vendor/dompdf/dompdf/lib/fonts')]);
        $options->setTempDir($runtime);
        $options->setFontCache($runtime);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($this->html($snapshot), 'UTF-8');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text($canvas->get_width() - 94, $canvas->get_height() - 26, 'Trang {PAGE_NUM}/{PAGE_COUNT}', $font, 8, [0.35, 0.4, 0.38]);
        $canvas->add_info('Title', $snapshot['kind'] === 'invoice' ? 'Hoa don quan ly nha tro' : 'Phieu thu quan ly nha tro');

        return $pdf->output();
    }

    private function response(array $snapshot, string $filename): Response
    {
        return new Response($this->bytes($snapshot), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function invoiceData(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id, 'number' => $invoice->invoice_number,
            'room' => $invoice->occupancy->room->room_number,
            'group' => $invoice->occupancy->rentalGroup->group_code,
            'billing_month' => $invoice->billing_month->format('m/Y'),
            'issued_at' => $invoice->issued_at->format('d/m/Y H:i:s'),
            'due_date' => $invoice->due_date?->format('d/m/Y'),
            'status' => $invoice->payment_status,
            'status_label' => ['unpaid' => 'Chưa thanh toán', 'partially_paid' => 'Thanh toán một phần',
                'paid' => 'Đã thanh toán đủ', 'cancelled' => 'Đã hủy', 'inconsistent' => 'Cần đối chiếu'][$invoice->payment_status] ?? 'Cần đối chiếu',
            'cancelled_at' => $invoice->cancelled_at?->format('d/m/Y H:i:s'),
            'original_amount' => InvoiceReports::money($invoice->original_amount),
            'adjustment_amount' => InvoiceReports::money($invoice->adjustment_amount),
            'total_amount' => InvoiceReports::money($invoice->total_amount),
            'paid_amount' => InvoiceReports::money($invoice->paid_amount),
            'outstanding_amount' => InvoiceReports::money($invoice->outstanding_amount),
            'items' => $invoice->items->map(fn ($item) => [
                'description' => $item->description, 'unit' => $item->unit,
                'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'period_start' => $item->charge_period_start?->format('d/m/Y H:i'),
                'period_end' => $item->charge_period_end?->format('d/m/Y H:i'),
                'meter' => $item->meterReading === null ? null : [
                    'previous' => $item->meterReading->previous_reading,
                    'current' => $item->meterReading->current_reading,
                    'consumption' => $item->meterReading->consumption,
                ],
            ])->all(),
            'changes' => $invoice->changes->map(fn ($change) => [
                'action' => $change->action, 'amount' => $change->amount, 'reason' => $change->reason,
                'created_at' => $change->created_at->format('d/m/Y H:i:s'),
                'label' => ['adjustment' => 'Điều chỉnh số tiền', 'due_date' => 'Đổi hạn thanh toán',
                    'cancel' => 'Hủy hóa đơn', 'restore' => 'Khôi phục hóa đơn'][$change->action] ?? 'Cập nhật hóa đơn',
            ])->all(),
        ];
    }

    private function payment(Payment $payment): array
    {
        $correction = $payment->correction;

        return [
            'id' => $payment->id, 'number' => 'PT-'.$payment->id,
            'amount' => $payment->amount,
            'paid_at' => $payment->paid_at->format('d/m/Y H:i:s'),
            'recorded_at' => $payment->recorded_at->format('d/m/Y H:i:s'),
            'method' => $payment->payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản',
            'voided' => $correction !== null,
            'replaces_id' => $payment->replacementFor?->payment_id,
            'correction' => $correction === null ? null : [
                'reason' => $correction->reason,
                'created_at' => $correction->created_at->format('d/m/Y H:i:s'),
                'replacement_id' => $correction->replacement_payment_id,
            ],
        ];
    }

    public static function decimal(mixed $value, int $scale = 2): string
    {
        $text = (string) BigDecimal::of((string) $value)->toScale($scale, RoundingMode::HALF_UP);
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        $fraction = rtrim($fraction, '0');

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).($fraction === '' ? '' : ','.$fraction);
    }

    public static function text(?string $value): string
    {
        return preg_replace_callback('/\S{35,}/u', fn ($match) => implode("\u{200B}", mb_str_split($match[0], 24)), $value ?? '') ?? '';
    }
}
