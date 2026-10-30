<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceChange;
use App\Models\MaintenanceRequest;
use App\Models\PaymentCorrection;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AppNotifications
{
    public function visible(User $actor): Builder
    {
        return AppNotification::query()->where('user_id', $actor->id)
            ->where(function ($query) use ($actor) {
                $query->where(function ($query) use ($actor) {
                    $query->whereNotNull('invoice_id')->whereExists(fn ($access) => $access->selectRaw('1')
                        ->from('active_invoice_access as access')->join('invoice_balances as balances', 'balances.invoice_id', '=', 'access.invoice_id')
                        ->whereColumn('access.invoice_id', 'app_notifications.invoice_id')->where('access.user_id', $actor->id)
                        ->where(fn ($visibility) => $visibility->where('app_notifications.type', 'invoice_updated')
                            ->orWhere(fn ($unpaid) => $unpaid->whereIn('balances.payment_status', ['unpaid', 'partially_paid'])->where('balances.outstanding_amount', '>', 0))));
                })->orWhere(function ($query) use ($actor) {
                    $query->whereNotNull('maintenance_request_id')->whereHas('maintenanceRequest', function ($query) use ($actor) {
                        if (! $actor->hasRole(User::ROLE_OWNER)) {
                            $query->where('submitted_by', $actor->id);
                        }
                    });
                });
                if ($actor->hasRole(User::ROLE_TENANT)) {
                    $query->orWhere(fn ($announcement) => $announcement->where('type', 'owner_announcement')->whereNotNull('announcement_id')->whereHas('announcement'));
                }
            });
    }

    public function maintenanceSubmitted(MaintenanceRequest $report): void
    {
        foreach (User::query()->where('role', User::ROLE_OWNER)->where('is_active', true)->orderBy('id')->cursor() as $owner) {
            $this->add($owner->id, 'maintenance_submitted', 'maintenance:'.$report->id.':submitted:'.$owner->id,
                ['title' => 'Có yêu cầu báo hỏng mới', 'body' => $report->title], maintenanceId: $report->id);
        }
    }

    public function invoiceUpdated(Invoice $invoice, InvoiceChange $change): void
    {
        $grant = InvoiceAccessGrant::query()->where('invoice_id', $invoice->id)->whereNull('revoked_at')->first();
        if ($grant !== null) {
            $titles = ['adjustment' => 'Hóa đơn có khoản điều chỉnh', 'due_date' => 'Hạn thanh toán được cập nhật',
                'cancel' => 'Hóa đơn đã được hủy', 'restore' => 'Hóa đơn được khôi phục'];
            $this->add($grant->user_id, 'invoice_updated', 'invoice:'.$invoice->id.':revision:'.$change->revision,
                ['title' => $titles[$change->action], 'body' => 'Hóa đơn '.$invoice->invoice_number.' — '.mb_substr($change->reason, 0, 400)], invoiceId: $invoice->id);
        }
    }

    public function maintenanceUpdated(MaintenanceRequest $report): void
    {
        $this->add($report->submitted_by, 'maintenance_updated', 'maintenance:'.$report->id.':revision:'.$report->revision,
            ['title' => $report->status === 'resolved' ? 'Yêu cầu sửa chữa đã hoàn thành' : 'Yêu cầu sửa chữa có cập nhật',
                'body' => mb_substr($report->resolution ?? 'Chủ trọ đã tiếp nhận yêu cầu.', 0, 500), 'status' => $report->status], maintenanceId: $report->id);
    }

    public function paymentCorrected(Invoice $invoice, PaymentCorrection $correction): void
    {
        $grant = InvoiceAccessGrant::query()->where('invoice_id', $invoice->id)->whereNull('revoked_at')->first();
        if ($grant !== null) {
            $this->add($grant->user_id, 'invoice_updated', 'payment-correction:'.$correction->id,
                ['title' => 'Phiếu thu hóa đơn được sửa sai', 'body' => 'Hóa đơn '.$invoice->invoice_number.' — '.mb_substr($correction->reason, 0, 400)], invoiceId: $invoice->id);
        }
    }

    /** @return array{notification: ?AppNotification, replayed: bool, skip_reason: ?string} */
    public function remind(Invoice $invoice, bool $overdueOnly = false): array
    {
        return DB::transaction(function () use ($invoice, $overdueOnly) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($overdueOnly && ($locked->due_date === null || $locked->due_date->format('Y-m-d') >= now()->format('Y-m-d'))) {
                return ['notification' => null, 'replayed' => false, 'skip_reason' => 'Hóa đơn chưa quá hạn.'];
            }
            $balance = DB::table('invoice_balances')->where('invoice_id', $locked->id)->first();
            if (! in_array($balance->payment_status, ['unpaid', 'partially_paid'], true) || ! BigDecimal::of($balance->outstanding_amount)->isPositive()) {
                return ['notification' => null, 'replayed' => false, 'skip_reason' => 'Hóa đơn không có công nợ hợp lệ.'];
            }
            $grant = InvoiceAccessGrant::query()->where('invoice_id', $locked->id)->whereNull('revoked_at')->lockForUpdate()->first();
            $user = $grant === null ? null : User::query()->lockForUpdate()->find($grant->user_id);
            if ($user === null || ! $user->is_active || ! $user->hasRole(User::ROLE_TENANT)) {
                return ['notification' => null, 'replayed' => false, 'skip_reason' => 'Hóa đơn chưa có người được cấp quyền xem đang hoạt động.'];
            }
            $overdue = $locked->due_date !== null && $locked->due_date->format('Y-m-d') < now()->format('Y-m-d');
            $notification = $this->add($user->id, 'invoice_reminder', 'invoice:'.$locked->id.':user:'.$user->id.':day:'.now()->format('Y-m-d'),
                ['title' => $overdue ? 'Hóa đơn đã quá hạn thanh toán' : 'Nhắc thanh toán hóa đơn',
                    'body' => 'Hóa đơn '.$locked->invoice_number.' còn khoản cần thanh toán.'.($locked->due_date ? ' Hạn đóng: '.$locked->due_date->format('d/m/Y').'.' : '')], invoiceId: $locked->id);

            return ['notification' => $notification, 'replayed' => ! $notification->wasRecentlyCreated, 'skip_reason' => null];
        }, 3);
    }

    public function remindOutstanding(array $filters = [], bool $dryRun = false): array
    {
        $counts = ['created' => 0, 'replayed' => 0, 'skipped' => 0, 'candidates' => 0];
        app(InvoiceReports::class)->outstanding($filters)->chunkById(100, function ($invoices) use (&$counts, $dryRun) {
            foreach ($invoices as $invoice) {
                $counts['candidates']++;
                if ($dryRun) {
                    continue;
                }
                $result = $this->remind($invoice, ($filters['due_status'] ?? null) === 'overdue');
                $counts[$result['notification'] === null ? 'skipped' : ($result['replayed'] ? 'replayed' : 'created')]++;
            }
        }, 'invoices.id', 'id');

        return $counts;
    }

    private function add(int $userId, string $type, string $key, array $data, ?int $invoiceId = null, ?int $maintenanceId = null): AppNotification
    {
        return AppNotification::firstOrCreate(['dedupe_key' => hash('sha256', $key)], [
            'user_id' => $userId, 'type' => $type, 'invoice_id' => $invoiceId,
            'maintenance_request_id' => $maintenanceId, 'data' => $data,
        ]);
    }
}
