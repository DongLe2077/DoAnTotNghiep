<?php

namespace App\Services;

use App\Http\Requests\Owner\CorrectPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentCorrections
{
    public static function version(Payment $payment): string
    {
        return hash_hmac('sha256', json_encode([$payment->id, $payment->invoice_id, $payment->amount,
            $payment->paid_at->format('Y-m-d H:i:s'), $payment->payment_method, $payment->correction?->id], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function correct(Payment $payment, array $data, User $actor): array
    {
        $request = new CorrectPaymentRequest;
        $data = Validator::make($data, $request->rules(), $request->messages(), $request->attributes())->validate();
        $values = ['action' => $data['action'], 'reason' => trim($data['reason']), 'version_token' => $data['version_token']];
        if ($data['action'] === 'replace') {
            $values['replacement'] = ['amount' => (string) BigDecimal::of((string) $data['replacement']['amount'])->toScale(2),
                'paid_at' => $data['replacement']['paid_at'], 'payment_method' => $data['replacement']['payment_method']];
        }
        $fingerprint = hash('sha256', json_encode([$payment->id, $actor->id, $values], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $operation = function () use ($payment, $actor, $data, $values, $fingerprint) {
            // Match the lock order of collection and invoice control operations.
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
            $currentActor = User::query()->findOrFail($actor->id);
            abort_unless($currentActor->is_active && $currentActor->hasRole(User::ROLE_OWNER), 403);
            $prior = PaymentCorrection::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($prior !== null) {
                abort_unless(hash_equals($prior->request_fingerprint, $fingerprint), 409, 'Mã yêu cầu đã dùng cho nội dung sửa phiếu thu khác.');

                return ['correction' => $prior, 'replayed' => true];
            }
            $receipt = Payment::query()->with('correction')->lockForUpdate()->findOrFail($payment->id);
            abort_unless(hash_equals(self::version($receipt), $values['version_token']), 409, 'Phiếu thu đã được xử lý. Hãy tải lại trước khi tiếp tục.');
            abort_if($receipt->correction !== null, 409, 'Phiếu thu đã được sửa sai; cần đối chiếu phiếu thay thế nếu có.');
            abort_if($invoice->cancelled_at !== null, 409, 'Hóa đơn đã hủy, cần đối chiếu trước khi sửa phiếu thu.');
            if ($values['action'] === 'replace') {
                $replacement = $values['replacement'];
                abort_if(BigDecimal::of($receipt->amount)->isEqualTo($replacement['amount'])
                    && $receipt->paid_at->format('Y-m-d H:i:s') === $replacement['paid_at']
                    && $receipt->payment_method === $replacement['payment_method'], 422, 'Thông tin phiếu thay thế không thay đổi.');
            }
            $before = app(InvoiceQueries::class)->query()->findOrFail($invoice->id);
            $correction = PaymentCorrection::create(['invoice_id' => $invoice->id, 'payment_id' => $receipt->id,
                'action' => $values['action'], 'reason' => $values['reason'], 'before_data' => $this->snapshot($before), 'after_data' => [],
                'performed_by' => $actor->id, 'actor_username' => $currentActor->username,
                'idempotency_key' => $data['idempotency_key'], 'request_fingerprint' => $fingerprint]);
            if ($values['action'] === 'replace') {
                // A stable internal key makes a corrected receipt distinct from any public retry key.
                try {
                    $replacement = app(InvoicePayments::class)->record($invoice, $values['replacement'] + [
                        'idempotency_key' => 'pc.'.substr(hash('sha256', $data['idempotency_key']), 0, 60),
                    ], $actor->id);
                } catch (ValidationException $error) {
                    $messages = [];
                    foreach ($error->errors() as $field => $errors) {
                        $messages['replacement.'.$field] = $errors;
                    }
                    throw ValidationException::withMessages($messages);
                }
                abort_if($replacement['replayed'], 409, 'Mã phiếu thay thế đã tồn tại. Hãy tải lại và dùng một yêu cầu sửa sai mới.');
                $correction->replacement_payment_id = $replacement['payment']->id;
            }
            $after = app(InvoiceQueries::class)->query()->findOrFail($invoice->id);
            abort_if(BigDecimal::of(InvoiceReports::money($after->paid_amount))->isNegative()
                || BigDecimal::of(InvoiceReports::money($after->paid_amount))->isGreaterThan(InvoiceReports::money($after->total_amount)),
                409, 'Số tiền còn hiệu lực chưa khớp hóa đơn. Cần đối chiếu trước khi xác nhận sửa sai.');
            $correction->after_data = $this->snapshot($after);
            $correction->save();
            $invoice->revision++;
            $invoice->save();
            app(AppNotifications::class)->paymentCorrected($invoice, $correction);

            return ['correction' => $correction, 'replayed' => false];
        };
        try {
            return DB::transaction($operation, 3);
        } catch (UniqueConstraintViolationException) {
            $prior = PaymentCorrection::query()->where('idempotency_key', $data['idempotency_key'])->first();
            abort_unless($prior !== null && hash_equals($prior->request_fingerprint, $fingerprint), 409, 'Yêu cầu sửa phiếu thu bị trùng. Hãy tải lại để đối chiếu.');

            return ['correction' => $prior, 'replayed' => true];
        }
    }

    private function snapshot(Invoice $invoice): array
    {
        return ['total_amount' => InvoiceReports::money($invoice->total_amount), 'paid_amount' => InvoiceReports::money($invoice->paid_amount),
            'outstanding_amount' => InvoiceReports::money($invoice->outstanding_amount), 'payment_status' => $invoice->payment_status];
    }
}
