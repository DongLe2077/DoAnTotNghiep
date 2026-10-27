<?php

namespace App\Services;

use App\Models\CashCreationRequest;
use App\Models\CashTransaction;
use App\Models\CashTransactionHistory;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashBook
{
    public function query(array $filters = []): EloquentBuilder
    {
        $query = CashTransaction::query();
        if (($filters['state'] ?? 'active') === 'all') {
            $query->withTrashed();
        } elseif (($filters['state'] ?? 'active') === 'deleted') {
            $query->onlyTrashed();
        }
        $this->applyDates($query, 'occurred_at', $filters);
        if (isset($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $query->whereRaw("description LIKE ? ESCAPE '!'", [$this->search($filters['q'])]);
        }

        return $query;
    }

    public function create(array $data, int $actorId): CashTransaction
    {
        return DB::transaction(function () use ($data, $actorId) {
            $entry = CashTransaction::create($this->values($data) + ['recorded_by' => $actorId])->refresh();
            $entry->revision = 1;
            $entry->save();
            $this->audit($entry, 'created', null, $actorId, $data['reason'] ?? null);

            return $entry;
        }, 3);
    }

    /** @return array{transaction: CashTransaction, replayed: bool} */
    public function record(array $data, int $actorId): array
    {
        $key = $data['idempotency_key'] ?? null;
        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Mã yêu cầu thu chi không hợp lệ.']);
        }
        $values = $this->values($data);
        $original = [
            'transaction_type' => $values['transaction_type'], 'amount' => $values['amount'],
            'occurred_at' => $values['occurred_at'], 'description' => $values['description'],
            'reason' => $data['reason'] ?? null,
        ];
        $fingerprint = hash('sha256', json_encode(['recorded_by' => $actorId, 'data' => $original], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        try {
            return DB::transaction(function () use ($key, $original, $fingerprint, $actorId) {
                $existing = CashCreationRequest::query()->where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing !== null) {
                    return $this->replayCreation($existing, $fingerprint, $actorId);
                }
                $entry = $this->create($original, $actorId);
                CashCreationRequest::create([
                    'idempotency_key' => $key, 'cash_transaction_id' => $entry->id, 'recorded_by' => $actorId,
                    'request_data' => $original, 'request_fingerprint' => $fingerprint,
                ]);

                return ['transaction' => $entry, 'replayed' => false];
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // Reconcile after rollback: a concurrent creator may have won this key.
            // Both its financial row and its creation audit remain one atomic unit.
            return DB::transaction(function () use ($key, $fingerprint, $actorId) {
                $existing = CashCreationRequest::query()->where('idempotency_key', $key)->lockForUpdate()->first();
                abort_if($existing === null, 409, 'Yêu cầu thu chi bị trùng. Hãy tải lại lịch sử thu chi.');

                return $this->replayCreation($existing, $fingerprint, $actorId);
            }, 3);
        }
    }

    private function replayCreation(CashCreationRequest $existing, string $fingerprint, int $actorId): array
    {
        abort_unless($existing->recorded_by === $actorId && hash_equals($existing->request_fingerprint, $fingerprint),
            409, 'Mã yêu cầu đã dùng cho khoản thu chi có thông tin khác. Giữ nguyên thông tin khi gửi lại; chỉ dùng mã mới cho khoản mới.');
        // Replay identifies the original entry but returns its current state, including deletion.
        $entry = CashTransaction::withTrashed()->lockForUpdate()->findOrFail($existing->cash_transaction_id);

        return ['transaction' => $entry, 'replayed' => true];
    }

    public function update(CashTransaction $transaction, array $data, int $actorId): CashTransaction
    {
        return DB::transaction(function () use ($transaction, $data, $actorId) {
            $locked = CashTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $this->assertVersion($locked, $data['version_token']);
            $before = $this->snapshot($locked);
            $locked->fill($this->values($data));
            if (! $locked->isDirty()) {
                return $locked;
            }
            $locked->revision++;
            $locked->save();
            $this->audit($locked, 'updated', $before, $actorId, $data['reason'] ?? null);

            return $locked->refresh();
        }, 3);
    }

    public function delete(CashTransaction $transaction, string $version, int $actorId, ?string $reason = null): void
    {
        DB::transaction(function () use ($transaction, $version, $actorId, $reason) {
            $locked = CashTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $this->assertVersion($locked, $version);
            $before = $this->snapshot($locked);
            $locked->deleted_at = now()->startOfSecond();
            $locked->revision++;
            $locked->save();
            $this->audit($locked, 'deleted', $before, $actorId, $reason);
        }, 3);
    }

    public function revert(int|string $id, array $data, int $actorId): CashTransaction
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $entry = CashTransaction::withTrashed()->lockForUpdate()->findOrFail($id);
            $this->assertVersion($entry, $data['version_token']);
            $history = $entry->histories()->orderByDesc('revision')->lockForUpdate()->first();
            abort_unless($history !== null && $history->id === (int) $data['history_id'], 409,
                'Chỉ hoàn tác thao tác mới nhất. Hãy tải lại nhật ký trước khi tiếp tục.');
            $before = $this->snapshot($entry);
            if ($history->before_data === null) {
                // Undo creation by retaining the record as a reversible deletion.
                $entry->deleted_at = now()->startOfSecond();
            } else {
                $entry->fill($this->values($history->before_data));
                $entry->deleted_at = $history->before_data['deleted_at'];
            }
            $entry->revision++;
            $entry->save();
            $this->audit($entry, 'reverted', $before, $actorId, $data['reason'], $history->id);

            return $entry->refresh();
        }, 3);
    }

    private function snapshot(CashTransaction $entry): array
    {
        return [
            'transaction_type' => $entry->transaction_type, 'amount' => $entry->amount,
            'occurred_at' => $entry->occurred_at->format('Y-m-d H:i:s'), 'description' => $entry->description,
            'recorded_by' => $entry->recorded_by, 'recorded_at' => $entry->recorded_at->format('Y-m-d H:i:s'),
            'deleted_at' => $entry->deleted_at?->format('Y-m-d H:i:s'), 'revision' => $entry->revision,
        ];
    }

    private function audit(CashTransaction $entry, string $action, ?array $before, int $actorId, ?string $reason, ?int $revertedId = null): void
    {
        CashTransactionHistory::create([
            'cash_transaction_id' => $entry->id, 'revision' => $entry->revision, 'action' => $action,
            'before_data' => $before, 'after_data' => $this->snapshot($entry), 'performed_by' => $actorId,
            'reason' => $reason, 'reverted_history_id' => $revertedId,
        ]);
    }

    public static function version(CashTransaction $transaction): string
    {
        return hash_hmac('sha256', json_encode([
            'cash_transaction', $transaction->id, $transaction->transaction_type, $transaction->amount,
            $transaction->occurred_at->format('Y-m-d H:i:s'), $transaction->description,
            $transaction->recorded_by, $transaction->recorded_at->format('Y-m-d H:i:s'),
            $transaction->revision, $transaction->deleted_at?->format('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), config('app.key'));
    }

    public function ledger(array $filters): Builder
    {
        // No invoice/items join is needed: each confirmed payment contributes exactly once.
        $payments = DB::table('payments')->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->selectRaw("payments.id AS source_id, 'payment' AS source, 'invoice_payment' AS entry_type,
                payments.amount, payments.paid_at AS occurred_at, NULL AS description,
                payments.recorded_by, payments.recorded_at, payments.invoice_id,
                invoices.invoice_number, payments.payment_method");
        $cash = DB::table('cash_transactions')->whereNull('deleted_at')->selectRaw("id AS source_id, 'cash_transaction' AS source,
                transaction_type AS entry_type, amount, occurred_at, description, recorded_by, recorded_at,
                NULL AS invoice_id, NULL AS invoice_number, NULL AS payment_method");
        $this->applyDates($payments, 'payments.paid_at', $filters);
        $this->applyDates($cash, 'occurred_at', $filters);
        $query = DB::query()->fromSub($payments->unionAll($cash), 'cash_flow');
        if (isset($filters['entry_type'])) {
            $query->where('entry_type', $filters['entry_type']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $pattern = $this->search($filters['q']);
            $query->where(fn ($query) => $query->whereRaw("description LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("invoice_number LIKE ? ESCAPE '!'", [$pattern]));
        }

        return $query;
    }

    public function summary(array $filters): array
    {
        $totals = $this->emptyTotals();
        $groups = [];
        $groupBy = $filters['group_by'] ?? 'month';
        // A single statement provides one consistent view of both receipt sources.
        foreach ($this->ledger($filters)->orderBy('occurred_at')->orderBy('source')->orderBy('source_id')->cursor() as $entry) {
            $period = substr($entry->occurred_at, 0, $groupBy === 'day' ? 10 : 7);
            $groups[$period] ??= $this->emptyTotals();
            $this->add($totals, $entry);
            $this->add($groups[$period], $entry);
        }
        [$from, $until] = $this->bounds($filters);

        return [
            'from' => $from, 'until' => $until, 'group_by' => $groupBy,
            'entry_type' => $filters['entry_type'] ?? null, 'q' => $filters['q'] ?? null,
            ...$this->finish($totals),
            'periods' => array_map(fn ($period, $values) => ['period' => $period] + $this->finish($values), array_keys($groups), array_values($groups)),
        ];
    }

    private function emptyTotals(): array
    {
        return ['invoice_payment_total' => BigDecimal::zero()->toScale(2), 'other_income_total' => BigDecimal::zero()->toScale(2),
            'expense_total' => BigDecimal::zero()->toScale(2), 'invoice_payment_count' => 0, 'other_income_count' => 0, 'expense_count' => 0];
    }

    private function add(array &$totals, object $entry): void
    {
        $totals[$entry->entry_type.'_total'] = $totals[$entry->entry_type.'_total']->plus((string) $entry->amount);
        $totals[$entry->entry_type.'_count']++;
    }

    private function finish(array $totals): array
    {
        $income = $totals['invoice_payment_total']->plus($totals['other_income_total']);

        return [
            'invoice_payment_total' => (string) $totals['invoice_payment_total']->toScale(2, RoundingMode::HALF_UP),
            'other_income_total' => (string) $totals['other_income_total']->toScale(2, RoundingMode::HALF_UP),
            'expense_total' => (string) $totals['expense_total']->toScale(2, RoundingMode::HALF_UP),
            'total_income' => (string) $income->toScale(2, RoundingMode::HALF_UP),
            'net_cash_flow' => (string) $income->minus($totals['expense_total'])->toScale(2, RoundingMode::HALF_UP),
            'invoice_payment_count' => $totals['invoice_payment_count'], 'other_income_count' => $totals['other_income_count'],
            'expense_count' => $totals['expense_count'],
            'entry_count' => $totals['invoice_payment_count'] + $totals['other_income_count'] + $totals['expense_count'],
        ];
    }

    private function bounds(array $filters): array
    {
        if (isset($filters['month'])) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month'], config('app.timezone'));

            return [$month->format('Y-m-01 00:00:00'), $month->format('Y-m-t 23:59:59')];
        }

        return [$filters['from'] ?? null, $filters['until'] ?? null];
    }

    private function applyDates(Builder|EloquentBuilder $query, string $column, array $filters): void
    {
        [$from, $until] = $this->bounds($filters);
        if ($from !== null) {
            $query->where($column, '>=', $from);
        }
        if ($until !== null) {
            $query->where($column, '<=', $until);
        }
    }

    private function values(array $data): array
    {
        $values = array_intersect_key($data, array_flip(['transaction_type', 'amount', 'occurred_at', 'description']));
        if (isset($values['amount'])) {
            $values['amount'] = (string) BigDecimal::of((string) $values['amount'])->toScale(2);
        }

        return $values;
    }

    private function assertVersion(CashTransaction $transaction, string $version): void
    {
        abort_unless(hash_equals(self::version($transaction), $version), 409, 'Khoản thu chi đã thay đổi. Hãy tải lại dữ liệu trước khi sửa hoặc xóa.');
    }

    private function search(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }
}
