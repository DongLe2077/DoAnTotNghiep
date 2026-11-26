<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceChange;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PaymentCorrection;
use App\Models\User;
use App\Services\Tenancy\PropertyContext;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AppNotifications
{
    public function welcome(User $user): void
    {
        $this->saveWelcome($user, 'welcome');
    }

    public function prepareWelcome(User $user, string $tenantName): void
    {
        $this->saveWelcome($user, 'welcome_pending', $tenantName);
    }

    private function saveWelcome(User $user, string $type, ?string $tenantName = null): void
    {
        $data = $this->welcomeData($user, $tenantName);
        if ($tenantName !== null) {
            $data['name'] = $tenantName;
        }

        DB::table('app_notifications')->insert([
            'user_id' => $user->id,
            'type' => $type,
            'dedupe_key' => hash('sha256', 'welcome:'.$user->id),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public function publishPreparedWelcome(User $user): void
    {
        // Only accounts provisioned from a legacy tenant profile have a pending welcome.
        if ($user->getRawOriginal('tenant_id') === null) {
            return;
        }

        DB::table('app_notifications')
            ->where('dedupe_key', hash('sha256', 'welcome:'.$user->id))
            ->where('user_id', $user->id)->where('type', 'welcome_pending')
            ->update(['type' => 'welcome', 'created_at' => now()]);
    }

    public function welcomeData(User $user, ?string $tenantName = null): array
    {
        $name = trim((string) ($user->full_name ?? $tenantName));
        $greeting = $name === '' ? 'Chào bạn' : 'Chào '.$name;
        $context = app(PropertyContext::class);
        $hasProperty = ! $context->enabled() || $context->id() !== null
            || DB::table('property_memberships')->where('user_id', $user->id)
                ->where('status', 'approved')->whereNull('revoked_at')->exists();
        $hint = match (true) {
            $user->hasRole(User::ROLE_OWNER) && $hasProperty => 'Bạn có thể theo dõi tình hình khu trọ ở Tổng quan và xem những việc cần xử lý trong Công việc.',
            $user->canCreateRentalProperty() && ! $hasProperty => 'Bạn có thể tạo khu trọ trong Khu trọ của bạn để thêm phòng và mời người thuê.',
            $hasProperty => 'Bạn có thể xem thông tin ở Phòng của tôi và theo dõi các khoản cần thanh toán trong Hóa đơn.',
            default => 'Nếu đã nhận mã mời từ chủ trọ, bạn có thể nhập mã trong Khu trọ của bạn.',
        };

        return ['title' => 'Chào mừng bạn đến với RentBase', 'body' => $greeting.'! '.$hint];
    }

    public function visible(User $actor): Builder
    {
        $context = app(\App\Services\Tenancy\PropertyContext::class);
        $inbox = AppNotification::withoutGlobalScope('property')->where('user_id', $actor->id);
        if ($context->enabled() && $context->id() === null) {
            return $inbox->where('type', 'welcome');
        }

        return $inbox->where(function ($query) use ($actor, $context) {
            $query->where('type', 'welcome')->orWhere(function ($business) use ($actor, $context) {
                if ($context->enabled()) {
                    $propertyId = $context->requireId();
                    $scope = app(\App\Services\Tenancy\PropertyQueries::class);
                    $business->where(function ($belongs) use ($propertyId, $scope) {
                        foreach (['invoice_id' => 'invoices', 'maintenance_request_id' => 'maintenance_requests', 'announcement_id' => 'announcements'] as $key => $table) {
                            $belongs->orWhereIn($key, $scope->scoped($propertyId, $table)->select($table.'.id'));
                        }
                    });
                }
                $this->restrictBusinessNotifications($business, $actor);
            });
        });
    }

    private function restrictBusinessNotifications(Builder $inbox, User $actor): void
    {
        $inbox            ->where(function ($query) use ($actor) {
                $query->where(function ($query) use ($actor) {
                    $query->whereNotNull('invoice_id')->whereExists(fn ($access) => $access->selectRaw('1')
                        ->from(app(\App\Services\Tenancy\PropertyContext::class)->enabled() ? 'invoice_access_grants as access' : 'active_invoice_access as access')->when(app(\App\Services\Tenancy\PropertyContext::class)->enabled(), fn ($q) => $q->whereNull('access.revoked_at'))->join('invoice_balances as balances', 'balances.invoice_id', '=', 'access.invoice_id')
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
        foreach (User::query()->when(app(\App\Services\Tenancy\PropertyContext::class)->enabled(), fn ($q) => $q->whereIn('id', DB::table('rental_properties')->where('id', app(\App\Services\Tenancy\PropertyContext::class)->requireId())->select('owner_user_id')), fn ($q) => $q->where('role', User::ROLE_OWNER))->where('is_active', true)->orderBy('id')->cursor() as $owner) {
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

    public function paymentConfirmed(Invoice $invoice, Payment $payment): void
    {
        $grant = InvoiceAccessGrant::query()->where('invoice_id', $invoice->id)->whereNull('revoked_at')->first();
        if ($grant !== null) {
            $this->add($grant->user_id, 'invoice_updated', 'payos-payment:'.$payment->id,
                ['title' => 'Thanh toán thành công', 'body' => 'Hóa đơn '.$invoice->invoice_number.' đã được thanh toán. Phiếu thu PT-'.$payment->id.'.'], invoiceId: $invoice->id);
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
            $balance = \App\Services\Tenancy\PropertyDatabase::table('invoice_balances')->where('invoice_id', $locked->id)->first();
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
