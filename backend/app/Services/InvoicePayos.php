<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PayosCheckout;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PayOS\Exceptions\PayOSException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class InvoicePayos
{
    public function __construct(
        private readonly PayosGateway $gateway,
        private readonly InvoiceQueries $invoices,
        private readonly InvoicePayments $payments,
        private readonly AppNotifications $notifications,
    ) {}

    public function state(Invoice $invoice): array
    {
        $checkout = $this->latest($invoice);
        if (app(ScheduledDeletions::class)->anyCoversInvoice($invoice->id)) {
            return ['status' => 'queued_for_deletion', 'payment_id' => null, 'checkout_url' => null, 'can_start' => false];
        }
        $status = $checkout?->status ?? 'idle';
        $currentVersion = $checkout && $checkout->invoice_version === InvoiceControls::version($invoice);
        if ($status === 'pending') {
            $status = $checkout->expires_at->isPast() ? 'expired' : ($currentVersion ? 'pending' : 'stale');
        }
        $paymentId = $status === 'paid' ? \App\Services\Tenancy\PropertyDatabase::table('invoice_payos_transfers')->where('checkout_id', $checkout->id)->whereNotNull('payment_id')->value('payment_id') : null;

        return [
            'status' => $status,
            'payment_id' => $paymentId === null ? null : (int) $paymentId,
            'checkout_url' => $status === 'pending' && $this->gateway->enabled() ? $checkout->checkout_url : null,
            'can_start' => $this->gateway->enabled() && $this->payableAmount($invoice) !== null
                && $status !== 'stale' && ($status !== 'review' || ! $currentVersion),
        ];
    }

    public function start(int $invoiceId, int $tenantId): array
    {
        abort_unless($this->gateway->enabled(), 503, 'Thanh toán qua payOS đang tạm ngưng.');
        $lock = Cache::lock('payos-checkout:'.$invoiceId, 30);
        abort_unless($lock->get(), 409, 'Link thanh toán đang được tạo. Vui lòng thử lại sau giây lát.');
        try {
            $visible = $this->invoices->query(tenantUserId: $tenantId)->findOrFail($invoiceId);
            $previous = $this->latest($visible);
            if ($previous?->status === 'pending' && $previous->link_id !== null && ! $previous->expires_at->isFuture()) {
                try {
                    $this->reconcileCheckout($previous);
                } catch (PayOSException) {
                    abort(502, 'Chưa kiểm tra được link thanh toán trước đó. Vui lòng thử lại sau.');
                }
                if ($previous->refresh()->status !== 'pending') {
                    return $this->state($this->invoices->query(tenantUserId: $tenantId)->findOrFail($invoiceId));
                }
            }
            $checkout = DB::transaction(function () use ($invoiceId, $tenantId) {
                Invoice::query()->lockForUpdate()->findOrFail($invoiceId);
                abort_if(app(ScheduledDeletions::class)->anyCoversInvoice($invoiceId), 409, 'Hóa đơn đang chờ xóa.');
                $invoice = $this->invoices->query(tenantUserId: $tenantId)->findOrFail($invoiceId);
                $amount = $this->payableAmount($invoice);
                abort_if($amount === null, 409, 'Hóa đơn này chưa thể thanh toán qua payOS.');
                $version = InvoiceControls::version($invoice);
                $existing = $this->latest($invoice);
                if ($existing?->status === 'pending' && $existing->expires_at->isFuture()) {
                    abort_unless($existing->invoice_version === $version, 409, 'Hóa đơn đã thay đổi. Vui lòng chờ link thanh toán cũ hết hạn.');

                    return $existing;
                }
                abort_if($existing?->status === 'review' && $existing->invoice_version === $version, 409, 'Giao dịch trước đang chờ chủ trọ đối chiếu.');

                return PayosCheckout::query()->create([
                    'invoice_id' => $invoiceId, 'order_code' => random_int(10000000000000, 99999999999999),
                    'amount' => $amount, 'invoice_version' => $version, 'expires_at' => now()->addMinutes(15),
                ]);
            }, 3);

            if ($checkout->checkout_url === null) {
                $this->openLink($checkout);
            }

            return $this->state($this->invoices->query(tenantUserId: $tenantId)->findOrFail($invoiceId));
        } finally {
            $lock->release();
        }
    }

    private function openLink(PayosCheckout $checkout): void
    {
        $invoiceUrl = $this->gateway->origin().'/tenant/invoices/'.$checkout->invoice_id;
        try {
            $link = $this->gateway->create([
                'orderCode' => $checkout->order_code, 'amount' => $checkout->amount,
                'description' => 'RentBase', 'expiredAt' => $checkout->expires_at->timestamp,
                'returnUrl' => $invoiceUrl.'?payos=return', 'cancelUrl' => $invoiceUrl.'?payos=cancel',
            ]);
        } catch (PayOSException) {
            // A lost response can leave a valid link at payOS. Recover the same order before offering a retry.
            try {
                $info = $this->gateway->get($checkout->order_code);
                $id = $info['id'] ?? '';
                $link = ['paymentLinkId' => $id, 'checkoutUrl' => 'https://pay.payos.vn/web/'.$id, ...$info];
            } catch (PayOSException) {
                abort(502, 'Chưa kết nối được payOS. Bấm thử lại để kiểm tra link đang chờ.');
            }
        }

        $id = $link['paymentLinkId'] ?? '';
        $url = $link['checkoutUrl'] ?? '';
        abort_unless(($link['orderCode'] ?? null) === $checkout->order_code && ($link['amount'] ?? null) === $checkout->amount
            && is_string($id) && preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $id)
            && is_string($url) && strlen($url) <= 512 && str_starts_with($url, 'https://pay.payos.vn/web/'), 502, 'Link thanh toán payOS chưa hợp lệ.');
        DB::transaction(function () use ($checkout, $id, $url) {
            $locked = PayosCheckout::query()->lockForUpdate()->findOrFail($checkout->id);
            abort_unless($locked->link_id === null || $locked->link_id === $id, 502, 'Mã link thanh toán payOS không khớp.');
            $locked->update(['link_id' => $id, 'checkout_url' => $url]);
        }, 3);
    }

    public function receive(array $event): void
    {
        $reference = $event['reference'] ?? null;
        $amount = $event['amount'] ?? null;
        $date = $event['transactionDateTime'] ?? null;
        $orderCode = $event['orderCode'] ?? null;
        $linkId = $event['paymentLinkId'] ?? null;
        abort_unless(($event['code'] ?? null) === '00' && ($event['currency'] ?? null) === 'VND'
            && is_int($orderCode) && is_int($amount) && $amount > 0
            && is_string($linkId) && preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $linkId)
            && is_string($reference) && preg_match('/^[a-zA-Z0-9 ._:\/-]{1,128}$/D', $reference)
            && is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $date), 400, 'Dữ liệu giao dịch payOS không hợp lệ.');

        $checkout = PayosCheckout::query()->where('order_code', $orderCode)->first();
        if ($checkout === null) {
            // payOS sends a signed sample during webhook registration. It has no local checkout.
            return;
        }

        $key = hash('sha256', app(\App\Services\Tenancy\PropertyContext::class)->enabled() && app(\App\Services\Tenancy\PropertyContext::class)->requireId() !== (int) config('tenancy.legacy_payos_property_id') ? app(\App\Services\Tenancy\PropertyContext::class)->requireId().':'.$reference : $reference);
        try {
            DB::transaction(function () use ($checkout, $key, $linkId, $amount, $date, $reference) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($checkout->invoice_id);
                $locked = PayosCheckout::query()->lockForUpdate()->findOrFail($checkout->id);
                abort_unless($locked->link_id === null || $locked->link_id === $linkId, 409, 'Mã link thanh toán không khớp.');
                if (\App\Services\Tenancy\PropertyDatabase::table('invoice_payos_transfers')->where('reference_key', $key)->exists()) {
                    return;
                }

                $paidAt = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $date, config('app.timezone'));
                abort_unless($paidAt && $paidAt->format('Y-m-d H:i:s') === $date, 400, 'Thời điểm giao dịch không hợp lệ.');
                $balance = $this->invoices->query()->findOrFail($invoice->id);
                $reason = match (true) {
                    $locked->status !== 'pending' => 'Link thanh toán đã có giao dịch trước.',
                    $locked->amount !== $amount => 'Số tiền thực nhận khác số tiền trên link thanh toán.',
                    $paidAt->greaterThan($locked->expires_at) => 'Giao dịch phát sinh sau khi link hết hạn.',
                    $locked->invoice_version !== InvoiceControls::version($balance) => 'Hóa đơn hoặc số dư đã thay đổi.',
                    default => null,
                };

                $payment = null;
                if ($reason === null) {
                    try {
                        // The issuer is the responsible owner. The source and bank reference are recorded separately.
                        $payment = $this->payments->record($invoice, [
                            'idempotency_key' => 'payos-'.substr($key, 0, 58), 'amount' => (string) $amount,
                            'payment_method' => 'bank_transfer', 'paid_at' => $date,
                        ], $invoice->issued_by, requireFullBalance: true)['payment'];
                    } catch (HttpExceptionInterface|ValidationException $error) {
                        if ($error instanceof HttpExceptionInterface && $error->getStatusCode() !== 409) {
                            throw $error;
                        }
                        $reason = 'Hóa đơn hoặc thời điểm thu cần được đối chiếu.';
                    }
                }

                \App\Services\Tenancy\PropertyDatabase::table('invoice_payos_transfers')->insert([
                    'checkout_id' => $locked->id, 'reference_key' => $key, 'reference' => $reference,
                    'amount' => $amount, 'paid_at' => $date, 'payment_id' => $payment?->id,
                    'review_reason' => $reason, 'created_at' => now(),
                ]);
                $locked->update(['link_id' => $linkId, 'status' => $reason === null ? 'paid' : 'review']);
                if ($payment !== null) {
                    $this->notifications->paymentConfirmed($invoice, $payment);
                }
            }, 3);
        } catch (UniqueConstraintViolationException $error) {
            if (! \App\Services\Tenancy\PropertyDatabase::table('invoice_payos_transfers')->where('reference_key', $key)->exists()) {
                throw $error;
            }
        }
    }

    public function reviewTransfers(Invoice $invoice): array
    {
        return \App\Services\Tenancy\PropertyDatabase::table('invoice_payos_transfers as transfers')
            ->join('invoice_payos_checkouts as checkouts', 'checkouts.id', '=', 'transfers.checkout_id')
            ->where('checkouts.invoice_id', $invoice->id)->whereNotNull('transfers.review_reason')
            ->orderByDesc('transfers.id')->limit(20)
            ->get(['transfers.id', 'transfers.amount', 'transfers.reference', 'transfers.paid_at', 'transfers.review_reason'])
            ->all();
    }

    public function reconcilePending(): void
    {
        $checkouts = PayosCheckout::query()->where('status', 'pending')->whereNotNull('link_id')
            ->where('expires_at', '>=', now()->subHours(2))
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<=', now()->subMinute()))
            ->orderBy('checked_at')->orderBy('id')->limit(6)->get();

        foreach ($checkouts as $checkout) {
            $checkout->forceFill(['checked_at' => now()])->save();
            try {
                $this->reconcileCheckout($checkout);
            } catch (PayOSException|HttpExceptionInterface $error) {
                Log::warning('Kiểm tra lại giao dịch payOS chưa hoàn tất.', ['order_code' => $checkout->order_code, 'type' => $error::class]);
            }
        }
    }

    private function reconcileCheckout(PayosCheckout $checkout): void
    {
        $info = $this->gateway->get($checkout->order_code);
        if (($info['id'] ?? null) !== $checkout->link_id || ($info['orderCode'] ?? null) !== $checkout->order_code
            || ($info['amount'] ?? null) !== $checkout->amount || ! is_array($info['transactions'] ?? null)) {
            throw new PayOSException('Kết quả kiểm tra link không khớp.');
        }
        foreach ($info['transactions'] as $transaction) {
            $this->receive(array_merge($transaction, [
                'code' => '00', 'currency' => 'VND', 'orderCode' => $checkout->order_code, 'paymentLinkId' => $checkout->link_id,
            ]));
        }
    }

    private function latest(Invoice $invoice): ?PayosCheckout
    {
        return PayosCheckout::query()->where('invoice_id', $invoice->id)->latest('id')->first();
    }

    private function payableAmount(Invoice $invoice): ?int
    {
        if (! in_array($invoice->payment_status, ['unpaid', 'partially_paid'], true)) {
            return null;
        }
        $amount = BigDecimal::of((string) $invoice->outstanding_amount)->stripTrailingZeros();

        return $amount->isPositive() && $amount->getScale() === 0 && $amount->isLessThanOrEqualTo(9999999999999) ? $amount->toInt() : null;
    }
}
