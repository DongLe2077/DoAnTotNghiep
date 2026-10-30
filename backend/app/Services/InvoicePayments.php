<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePayments
{
    public function query(array $filters = []): Builder
    {
        $query = Payment::query()->with('invoice.occupancy.room', 'invoice.occupancy.rentalGroup', 'correction', 'replacementFor');
        if (($filters['status'] ?? null) === 'active') {
            $query->effective();
        } elseif (($filters['status'] ?? null) === 'voided') {
            $query->whereHas('correction');
        }
        foreach (['invoice_id', 'payment_method'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['room_id', 'rental_group_id'] as $field) {
            if (isset($filters[$field])) {
                $query->whereHas('invoice.occupancy', fn ($occupancy) => $occupancy->where($field, $filters[$field]));
            }
        }
        if (isset($filters['paid_from'])) {
            $query->where('paid_at', '>=', $filters['paid_from']);
        }
        if (isset($filters['paid_until'])) {
            $query->where('paid_at', '<=', $filters['paid_until']);
        }

        return $query;
    }

    /** @return array{payment: Payment, replayed: bool} */
    public function record(Invoice $invoice, array $data, int $actorId): array
    {
        $values = $this->values($data);
        try {
            return DB::transaction(function () use ($invoice, $values, $actorId) {
                // All receipts for the same invoice serialize on its immutable parent row.
                $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                $existing = Payment::query()->where('idempotency_key', $values['idempotency_key'])->lockForUpdate()->first();
                if ($existing !== null) {
                    return $this->replay($existing, $locked, $values, $actorId);
                }
                abort_if($locked->cancelled_at !== null, 409, 'Hóa đơn đã hủy, không thể ghi nhận thu tiền.');
                if (CarbonImmutable::parse($values['paid_at'], config('app.timezone'))->lessThan($locked->issued_at)) {
                    throw ValidationException::withMessages(['paid_at' => 'Thời điểm thu tiền phải từ lúc phát hành hóa đơn trở đi.']);
                }

                // Use current locking reads rather than an aggregate from an old MySQL snapshot.
                $items = $locked->items()->orderBy('id')->lockForUpdate()->get();
                abort_if($items->isEmpty(), 409, 'Hóa đơn chưa có dòng tính tiền, cần kiểm tra trước khi thu.');
                $total = BigDecimal::zero()->toScale(2);
                foreach ($items as $item) {
                    abort_if($item->line_total === null || BigDecimal::of($item->line_total)->isNegative(), 409, 'Hóa đơn có dòng tính tiền không hợp lệ.');
                    $total = $total->plus($item->line_total);
                }
                foreach ($locked->changes()->where('action', 'adjustment')->orderBy('revision')->lockForUpdate()->get() as $change) {
                    $total = $total->plus($change->amount);
                }
                abort_if($total->isNegative(), 409, 'Tổng sau điều chỉnh không hợp lệ.');
                $paid = BigDecimal::zero()->toScale(2);
                foreach ($locked->payments()->effective()->orderBy('id')->lockForUpdate()->get() as $payment) {
                    abort_unless(BigDecimal::of($payment->amount)->isPositive(), 409, 'Lịch sử thu tiền không hợp lệ, cần kiểm tra dữ liệu.');
                    $paid = $paid->plus($payment->amount);
                }
                abort_if($paid->isGreaterThan($total), 409, 'Lịch sử thu tiền vượt tổng hóa đơn, cần kiểm tra dữ liệu.');
                $outstanding = $total->minus($paid);
                abort_if($outstanding->isZero(), 409, 'Hóa đơn đã thanh toán đủ, không thể thu thêm.');
                abort_if(BigDecimal::of($values['amount'])->isGreaterThan($outstanding), 409, 'Số tiền thu vượt số còn nợ '.$outstanding.'.');
                $payment = Payment::create($values + ['invoice_id' => $locked->id, 'recorded_by' => $actorId]);

                return ['payment' => $payment, 'replayed' => false];
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // Different invoices can race on the globally unique request key.
            // Reconcile only after rollback, then compare the complete original payload.
            return DB::transaction(function () use ($invoice, $values, $actorId) {
                $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                $existing = Payment::query()->where('idempotency_key', $values['idempotency_key'])->lockForUpdate()->first();
                abort_if($existing === null, 409, 'Yêu cầu thu tiền bị trùng. Hãy tải lại lịch sử thu tiền.');

                return $this->replay($existing, $locked, $values, $actorId);
            }, 3);
        }
    }

    private function replay(Payment $existing, Invoice $invoice, array $values, int $actorId): array
    {
        abort_unless($existing->invoice_id === $invoice->id
            && $existing->recorded_by === $actorId
            && BigDecimal::of($existing->amount)->isEqualTo($values['amount'])
            && $existing->paid_at->format('Y-m-d H:i:s') === $values['paid_at']
            && $existing->payment_method === $values['payment_method'],
            409, 'Mã yêu cầu đã dùng cho một lần thu có thông tin khác. Giữ nguyên thông tin khi gửi lại; chỉ dùng mã mới cho lần thu mới.');

        return ['payment' => $existing, 'replayed' => true];
    }

    private function values(array $data): array
    {
        $raw = $data['amount'] ?? null;
        if ((! is_string($raw) && ! is_int($raw) && ! is_float($raw))
            || ! preg_match('/^\d{1,16}(?:\.\d{1,2})?$/D', (string) $raw)
            || ! BigDecimal::of((string) $raw)->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Số tiền phải lớn hơn 0, tối đa 16 chữ số nguyên và 2 chữ số thập phân.']);
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $data['paid_at'] ?? '', config('app.timezone'));
        } catch (\Exception) {
            $date = null;
        }
        if ($date === null || $date === false || $date->format('Y-m-d H:i:s') !== ($data['paid_at'] ?? null)
            || $date->year < 1000 || $date->isFuture()) {
            throw ValidationException::withMessages(['paid_at' => 'Thời điểm thu tiền cần đúng định dạng năm-tháng-ngày giờ:phút:giây và không ở tương lai.']);
        }
        if (! in_array($data['payment_method'] ?? null, ['cash', 'bank_transfer'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Hình thức thu tiền không hợp lệ.']);
        }
        if (! is_string($data['idempotency_key'] ?? null) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D', $data['idempotency_key'])) {
            throw ValidationException::withMessages(['idempotency_key' => 'Mã yêu cầu thu tiền không hợp lệ.']);
        }

        return [
            'amount' => (string) BigDecimal::of((string) $raw)->toScale(2),
            'paid_at' => $date->format('Y-m-d H:i:s'),
            'payment_method' => $data['payment_method'], 'idempotency_key' => $data['idempotency_key'],
        ];
    }
}
