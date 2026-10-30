<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceChange;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InvoiceControls
{
    public static function version(Invoice $invoice): string
    {
        return hash_hmac('sha256', json_encode([$invoice->id, $invoice->revision, $invoice->due_date?->format('Y-m-d'),
            $invoice->cancelled_at?->format('Y-m-d H:i:s'), InvoiceReports::money($invoice->total_amount),
            InvoiceReports::money($invoice->paid_amount), $invoice->payment_status, (int) $invoice->item_count], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public static function dueStatus(Invoice $invoice): string
    {
        if (in_array($invoice->payment_status, ['cancelled', 'paid', 'inconsistent'], true)) {
            return $invoice->payment_status;
        }
        if ($invoice->due_date === null) {
            return 'not_set';
        }

        return $invoice->due_date->format('Y-m-d') < now()->format('Y-m-d') ? 'overdue' : 'upcoming';
    }

    public function change(Invoice $invoice, array $data, int $actorId): array
    {
        $values = ['action' => $data['action'], 'reason' => trim($data['reason']), 'version_token' => $data['version_token']];
        abort_if($values['reason'] === '', 422, 'Cần ghi rõ lý do thay đổi hóa đơn.');
        if ($data['action'] === 'adjustment') {
            $values['amount'] = (string) BigDecimal::of((string) $data['amount'])->toScale(2);
            abort_if(BigDecimal::of($values['amount'])->isZero(), 422, 'Khoản điều chỉnh phải khác 0.');
        }
        if ($data['action'] === 'due_date') {
            $values['due_date'] = $data['due_date'];
        }
        $fingerprint = hash('sha256', json_encode([$invoice->id, $actorId, $values], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $operation = function () use ($invoice, $values, $data, $fingerprint, $actorId) {
            // All invoice changes and receipts serialize on this same parent lock.
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $prior = InvoiceChange::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($prior !== null) {
                abort_unless(hash_equals($prior->request_fingerprint, $fingerprint), 409, 'Mã yêu cầu đã được dùng cho nội dung khác.');

                return ['invoice' => $locked, 'replayed' => true];
            }
            $current = app(InvoiceQueries::class)->query()->findOrFail($locked->id);
            abort_unless(hash_equals(self::version($current), $values['version_token']), 409, 'Hóa đơn hoặc số tiền đã thu đã thay đổi. Hãy tải lại và kiểm tra trước khi xác nhận.');
            abort_if($current->payment_status === 'inconsistent', 409, 'Cần đối chiếu dữ liệu hóa đơn trước khi thay đổi.');
            $action = $values['action'];
            $before = $this->snapshot($current);
            $amount = $values['amount'] ?? '0.00';
            if ($action === 'restore') {
                abort_if($locked->cancelled_at === null, 409, 'Hóa đơn đang có hiệu lực.');
                $locked->cancelled_at = null;
            } else {
                abort_if($locked->cancelled_at !== null, 409, 'Hóa đơn đã hủy. Cần khôi phục trước khi thay đổi.');
                if ($action === 'cancel') {
                    abort_if($locked->payments()->effective()->lockForUpdate()->first() !== null, 409, 'Hóa đơn còn phiếu thu có hiệu lực nên không được hủy. Cần đối chiếu các lần thu trước.');
                    $locked->cancelled_at = now()->startOfSecond();
                } elseif ($action === 'due_date') {
                    abort_if($values['due_date'] < now()->format('Y-m-d'), 422, 'Hạn thanh toán mới phải từ hôm nay trở đi.');
                    abort_if($locked->due_date?->format('Y-m-d') === $values['due_date'], 409, 'Hạn thanh toán không thay đổi.');
                    $locked->due_date = $values['due_date'];
                } else {
                    $next = BigDecimal::of(InvoiceReports::money($current->total_amount))->plus($amount);
                    abort_if($next->isLessThan(InvoiceReports::money($current->paid_amount)) || $next->isNegative(), 409, 'Tổng sau điều chỉnh không được âm hoặc thấp hơn số tiền đã thu.');
                    abort_if($next->isGreaterThan('9999999999999999.99'), 422, 'Tổng hóa đơn sau điều chỉnh vượt giới hạn cho phép.');
                }
            }
            $locked->revision++;
            $locked->save();
            $change = InvoiceChange::create(['invoice_id' => $locked->id, 'revision' => $locked->revision, 'action' => $action,
                'amount' => $amount, 'reason' => $values['reason'], 'before_data' => $before, 'after_data' => [],
                'performed_by' => $actorId, 'idempotency_key' => $data['idempotency_key'], 'request_fingerprint' => $fingerprint]);
            $after = app(InvoiceQueries::class)->query()->findOrFail($locked->id);
            $change->after_data = $this->snapshot($after);
            $change->save();
            app(AppNotifications::class)->invoiceUpdated($locked, $change);

            return ['invoice' => $locked, 'replayed' => false];
        };
        try {
            return DB::transaction($operation, 3);
        } catch (UniqueConstraintViolationException) {
            // A key used concurrently on another invoice is reconciled after rollback.
            $prior = InvoiceChange::query()->where('idempotency_key', $data['idempotency_key'])->first();
            abort_unless($prior !== null && hash_equals($prior->request_fingerprint, $fingerprint), 409, 'Mã yêu cầu đã được dùng cho nội dung khác.');

            return ['invoice' => $invoice->refresh(), 'replayed' => true];
        }
    }

    private function snapshot(Invoice $invoice): array
    {
        return ['total_amount' => InvoiceReports::money($invoice->total_amount), 'paid_amount' => InvoiceReports::money($invoice->paid_amount),
            'outstanding_amount' => InvoiceReports::money($invoice->outstanding_amount), 'due_date' => $invoice->due_date?->format('Y-m-d'),
            'payment_status' => $invoice->payment_status, 'cancelled_at' => $invoice->cancelled_at?->format('Y-m-d H:i:s')];
    }
}
