<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\MaintenanceExpenseHistory;
use App\Models\MaintenanceExpenseLink;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class MaintenanceExpenses
{
    public function query(MaintenanceRequest $report): Builder
    {
        return MaintenanceExpenseLink::query()->where('maintenance_request_id', $report->id)->where('is_active', true)
            ->with(['cashTransaction', 'maintenanceRequest'])->orderByDesc('id');
    }

    public function summary(MaintenanceRequest $report): array
    {
        $total = BigDecimal::zero()->toScale(2);
        $active = 0;
        $deleted = 0;
        foreach ($this->query($report)->get() as $link) {
            $cash = $link->cashTransaction;
            if ($cash->trashed()) {
                $deleted++;
            } elseif ($cash->transaction_type === 'expense') {
                $total = $total->plus($cash->amount);
                $active++;
            }
        }

        return ['total_amount' => (string) $total, 'active_count' => $active, 'deleted_count' => $deleted];
    }

    public function context(int $cashId): ?MaintenanceExpenseLink
    {
        CashTransaction::withTrashed()->findOrFail($cashId);

        return MaintenanceExpenseLink::query()->with(['cashTransaction', 'maintenanceRequest'])
            ->where('cash_transaction_id', $cashId)->first();
    }

    /** @return array{link: MaintenanceExpenseLink, replayed: bool, history_id: int} */
    public function link(MaintenanceRequest $report, array $data, int $actorId): array
    {
        return $this->mutate($report, (int) $data['cash_transaction_id'], 'linked', $data, $actorId);
    }

    /** @return array{link: MaintenanceExpenseLink, replayed: bool, history_id: int} */
    public function unlink(MaintenanceRequest $report, int $cashId, array $data, int $actorId): array
    {
        return $this->mutate($report, $cashId, 'unlinked', $data, $actorId);
    }

    private function mutate(MaintenanceRequest $report, int $cashId, string $action, array $data, int $actorId): array
    {
        $values = ['cash_transaction_id' => $cashId, 'maintenance_request_id' => $report->id, 'action' => $action,
            'cash_version_token' => $action === 'linked' ? $data['cash_version_token'] : null,
            'version_token' => $data['version_token'] ?? null, 'reason' => $data['reason'],
            'idempotency_key' => $data['idempotency_key'], 'performed_by' => $actorId];
        $fingerprint = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            return DB::transaction(function () use ($report, $cashId, $action, $data, $actorId, $fingerprint) {
                // Shared lock order with CashBook: the financial row always precedes its association.
                $cash = CashTransaction::withTrashed()->lockForUpdate()->findOrFail($cashId);
                $actor = User::query()->findOrFail($actorId);
                abort_unless($actor->is_active && $actor->hasRole(User::ROLE_OWNER), 403);
                $existing = MaintenanceExpenseHistory::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
                if ($existing !== null) {
                    return $this->replay($existing, $fingerprint);
                }
                MaintenanceRequest::query()->findOrFail($report->id);
                $link = MaintenanceExpenseLink::query()->where('cash_transaction_id', $cashId)->lockForUpdate()->first();
                $before = $link === null ? null : $this->snapshot($link, $cash);
                if ($action === 'linked') {
                    abort_if($cash->trashed(), 409, 'Khoản chi đã xóa. Khôi phục khoản chi trước khi liên kết.');
                    abort_unless($cash->transaction_type === 'expense', 409, 'Chỉ có thể liên kết khoản chi với sửa chữa.');
                    abort_unless(hash_equals(CashBook::version($cash), $data['cash_version_token']), 409,
                        'Khoản chi đã thay đổi. Hãy tải lại và kiểm tra số tiền trước khi liên kết.');
                    if ($link !== null) {
                        abort_if($link->is_active, 409, 'Khoản chi đã được liên kết với một yêu cầu. Hãy kiểm tra hoặc gỡ liên kết cũ trước.');
                        $this->assertVersion($link, $data['version_token'] ?? '');
                        $link->maintenance_request_id = $report->id;
                        $link->is_active = true;
                        $link->revision++;
                        $link->save();
                    } else {
                        abort_if(($data['version_token'] ?? null) !== null, 409, 'Thông tin liên kết đã thay đổi. Hãy tải lại khoản chi.');
                        $link = MaintenanceExpenseLink::create(['cash_transaction_id' => $cashId,
                            'maintenance_request_id' => $report->id, 'is_active' => true, 'revision' => 1]);
                    }
                } else {
                    abort_unless($link !== null && $link->is_active && $link->maintenance_request_id === $report->id,
                        409, 'Khoản chi không còn liên kết với yêu cầu này. Hãy tải lại danh sách.');
                    $this->assertVersion($link, $data['version_token']);
                    $link->is_active = false;
                    $link->revision++;
                    $link->save();
                }
                $history = MaintenanceExpenseHistory::create(['maintenance_expense_link_id' => $link->id,
                    'maintenance_request_id' => $report->id, 'cash_transaction_id' => $cashId, 'revision' => $link->revision,
                    'action' => $action, 'before_data' => $before, 'after_data' => $this->snapshot($link, $cash),
                    'performed_by' => $actorId, 'actor_username' => $actor->username, 'actor_role' => $actor->role,
                    'reason' => $data['reason'], 'idempotency_key' => $data['idempotency_key'], 'request_fingerprint' => $fingerprint]);

                return ['link' => $link->load(['cashTransaction', 'maintenanceRequest']), 'replayed' => false, 'history_id' => $history->id];
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(function () use ($cashId, $data, $fingerprint, $actorId) {
                CashTransaction::withTrashed()->lockForUpdate()->findOrFail($cashId);
                $actor = User::query()->findOrFail($actorId);
                abort_unless($actor->is_active && $actor->hasRole(User::ROLE_OWNER), 403);
                $existing = MaintenanceExpenseHistory::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
                abort_if($existing === null, 409, 'Liên kết đã được cập nhật đồng thời. Hãy tải lại trước khi tiếp tục.');

                return $this->replay($existing, $fingerprint);
            }, 3);
        }
    }

    private function replay(MaintenanceExpenseHistory $history, string $fingerprint): array
    {
        abort_unless(hash_equals($history->request_fingerprint, $fingerprint), 409,
            'Mã yêu cầu đã dùng với nội dung khác. Giữ nguyên yêu cầu khi thử lại.');
        $link = MaintenanceExpenseLink::query()->with(['cashTransaction', 'maintenanceRequest'])->findOrFail($history->maintenance_expense_link_id);

        return ['link' => $link, 'replayed' => true, 'history_id' => $history->id];
    }

    private function assertVersion(MaintenanceExpenseLink $link, string $token): void
    {
        abort_unless(hash_equals(self::version($link), $token), 409,
            'Liên kết đã thay đổi. Hãy tải lại bản mới nhất trước khi tiếp tục.');
    }

    private function snapshot(MaintenanceExpenseLink $link, CashTransaction $cash): array
    {
        return ['maintenance_request_id' => $link->maintenance_request_id, 'cash_transaction_id' => $link->cash_transaction_id,
            'is_active' => $link->is_active, 'revision' => $link->revision,
            'cash_snapshot' => ['transaction_type' => $cash->transaction_type, 'amount' => $cash->amount,
                'occurred_at' => $cash->occurred_at->format('Y-m-d H:i:s'), 'description' => $cash->description,
                'revision' => $cash->revision, 'is_deleted' => $cash->trashed()]];
    }

    public static function version(MaintenanceExpenseLink $link): string
    {
        return hash_hmac('sha256', json_encode(['maintenance_expense_link', $link->id, $link->cash_transaction_id,
            $link->maintenance_request_id, $link->is_active, $link->revision], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
