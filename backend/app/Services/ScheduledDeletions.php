<?php

namespace App\Services;

use App\Models\ScheduledDeletion;
use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ScheduledDeletions
{
    private const SCOPE_CHANGED = 'Có dữ liệu liên quan phát sinh sau khi đặt lịch. Hãy hủy lịch và xác nhận lại.';

    private const PAYMENT_PENDING = 'Có giao dịch payOS đang xử lý hoặc cần đối chiếu. Hãy kiểm tra hóa đơn trước khi xóa.';

    private const JOB_MISSING = 'Lịch xóa không còn tồn tại.';

    public function __construct(private readonly DeletionPlan $plan) {}

    public function preview(string $type, int $id): array
    {
        return DB::transaction(function () use ($type, $id) {
            $plan = $this->plan->collect($type, $id);

            return ['label' => $plan['label'], 'impact' => $this->impact($plan['records']),
                'confirmation' => $this->confirmation($type, $id, $plan['records']),
                'archives_monthly_totals' => app(MonthlyOccupancy::class)->enabled()];
        }, 3);
    }

    public function schedule(string $type, int $id, string $confirmation): ScheduledDeletion
    {
        return DB::transaction(function () use ($type, $id, $confirmation) {
            $plan = $this->plan->collect($type, $id);
            $pending = ScheduledDeletion::query()->where('record_type', $type)->where('record_id', $id)->lockForUpdate()->first();
            if ($pending !== null) {
                return $pending;
            }
            abort_unless(hash_equals($this->confirmation($type, $id, $plan['records']), $confirmation), 409,
                'Dữ liệu liên quan đã thay đổi. Hãy mở lại hộp thoại để kiểm tra trước khi xóa.');
            $otherJobs = ScheduledDeletion::query()->whereNull('photo_paths')->orderBy('id')->lockForUpdate()->get();
            foreach ($otherJobs as $other) {
                foreach ($this->confirmedRecords($plan['records']) as $table => $ids) {
                    abort_if(array_intersect($ids, $other->records[$table] ?? []) !== [], 409,
                        'Dữ liệu này nằm trong một lịch xóa khác. Mở Chờ xóa để xử lý lịch đó.');
                }
            }

            return ScheduledDeletion::create([
                'record_type' => $type, 'record_id' => $id, 'label' => $plan['label'],
                'records' => $plan['records'], 'purge_at' => now()->addDays(3),
            ]);
        }, 3);
    }

    public function previewPurge(int $jobId): array
    {
        return DB::transaction(function () use ($jobId) {
            $work = ScheduledDeletion::query()->find($jobId);
            abort_if($work === null, 404, self::JOB_MISSING);
            abort_if($work->photo_paths !== null, 409, 'Dữ liệu đã xóa. Hệ thống đang dọn ảnh.');
            $plan = $this->plan->collect($work->record_type, $work->record_id);
            $job = ScheduledDeletion::query()->lockForUpdate()->find($jobId);
            abort_if($job === null, 404, self::JOB_MISSING);
            abort_if($job->problem !== null, 409, $job->problem ?? '');
            abort_unless($this->withinScheduledScope($job, $plan['records']), 409, self::SCOPE_CHANGED);
            abort_if($this->hasUnresolvedPayment($plan['records']), 409, self::PAYMENT_PENDING);

            return [
                'label' => $plan['label'],
                'impact' => $this->impact($plan['records']),
                'confirmation' => $this->purgeConfirmation($jobId, $plan['records']),
                'archives_monthly_totals' => app(MonthlyOccupancy::class)->enabled(),
            ];
        }, 3);
    }

    public function purgeNow(int $jobId, string $confirmation): void
    {
        $work = ScheduledDeletion::query()->find($jobId);
        if ($work === null) {
            $this->assertPurgeReceipt($jobId, $confirmation);

            return;
        }

        $this->purge($work, $confirmation);
    }

    public function cancel(int $jobId): void
    {
        DB::transaction(function () use ($jobId) {
            $job = ScheduledDeletion::query()->lockForUpdate()->findOrFail($jobId);
            abort_if($job->photo_paths !== null, 409, 'Dữ liệu đã xóa, hệ thống đang dọn ảnh.');
            $job->delete();
        }, 3);
    }

    public function anyCoversInvoice(int $id): bool
    {
        return Schema::hasTable('scheduled_deletions')
            && ScheduledDeletion::query()->whereNull('photo_paths')->whereJsonContains('records->invoices', $id)->exists();
    }

    public function run(): int
    {
        if (! Schema::hasTable('scheduled_deletions')) {
            return 0;
        }
        $failures = 0;
        ScheduledDeletion::query()->where('purge_at', '<=', now())->whereNull('problem')->chunkById(50, function ($jobs) use (&$failures) {
            foreach ($jobs as $job) {
                try {
                    $this->purge($job);
                } catch (Throwable $error) {
                    report($error);
                    $failures++;
                    ScheduledDeletion::query()->whereKey($job->id)->whereNull('photo_paths')
                        ->update(['problem' => 'Xóa chưa hoàn tất. Dữ liệu vẫn còn. Hãy kiểm tra và đặt lịch lại.']);
                }
            }
        });

        return $failures;
    }

    private function purge(ScheduledDeletion $work, ?string $confirmation = null): void
    {
        DB::transaction(function () use ($work, $confirmation) {
            [$table] = DeletionPlan::TARGETS[$work->record_type];
            $target = PropertyDatabase::table($table)->where('id', $work->record_id)->lockForUpdate()->first();
            $job = ScheduledDeletion::query()->lockForUpdate()->find($work->id);
            if ($job === null || $job->photo_paths !== null) {
                if ($confirmation !== null) {
                    $this->assertPurgeReceipt($work->id, $confirmation);
                }

                return;
            }
            if ($confirmation === null && ($job->purge_at->isFuture() || $job->problem !== null)) {
                return;
            }
            abort_if($job->problem !== null, 409, $job->problem ?? '');
            if ($target === null) {
                abort_if($confirmation !== null, 404, 'Mục này không còn tồn tại.');
                $job->delete();

                return;
            }

            $records = $this->plan->collect($job->record_type, $job->record_id)['records'];
            if (! $this->withinScheduledScope($job, $records)) {
                abort_if($confirmation !== null, 409, self::SCOPE_CHANGED);
                $job->update(['problem' => self::SCOPE_CHANGED]);

                return;
            }
            if ($this->hasUnresolvedPayment($records)) {
                abort_if($confirmation !== null, 409, self::PAYMENT_PENDING);

                return;
            }
            if ($confirmation !== null) {
                abort_unless(hash_equals($this->purgeConfirmation($job->id, $records), $confirmation), 409,
                    'Dữ liệu liên quan đã thay đổi. Hãy kiểm tra lại phạm vi trước khi xóa.');
            }

            $report = app(MonthlyOccupancy::class);
            if ($report->enabled()) {
                app(MonthlyReportArchive::class)->beforeDeletion($records);
                $report->removeRooms($records['rooms'] ?? []);
            }
            $paths = $this->plan->purge($records);
            if ($confirmation !== null) {
                DB::table('scheduled_deletion_receipts')->insert([
                    'job_id' => $job->id,
                    'property_id' => app(PropertyContext::class)->id(),
                    'confirmation' => $confirmation,
                ]);
            }
            if ($paths !== []) {
                $job->update(['photo_paths' => $paths, 'purge_at' => now()]);
            } else {
                $job->delete();
            }
        }, 3);

        try {
            $this->cleanPhotos($work->id);
        } catch (Throwable $error) {
            if ($confirmation === null) {
                throw $error;
            }

            // The database deletion succeeded; the scheduled worker retries photo cleanup.
            report($error);
        }
    }

    private function cleanPhotos(int $jobId): void
    {
        $paths = ScheduledDeletion::query()->find($jobId)?->photo_paths;
        if ($paths === null) {
            return;
        }
        if (! Storage::disk('maintenance')->delete($paths)) {
            throw new RuntimeException('Could not remove photos for scheduled deletion '.$jobId);
        }
        ScheduledDeletion::query()->whereKey($jobId)->whereNotNull('photo_paths')->delete();
    }

    private function withinScheduledScope(ScheduledDeletion $job, array $records): bool
    {
        foreach ($this->confirmedRecords($records) as $table => $ids) {
            if (array_diff($ids, $job->records[$table] ?? []) !== []) {
                return false;
            }
        }

        return true;
    }

    private function hasUnresolvedPayment(array $records): bool
    {
        if (empty($records['invoice_payos_checkouts'])) {
            return false;
        }

        // Allow the same time for delayed transfers as payOS reconciliation.
        return PropertyDatabase::table('invoice_payos_checkouts')->whereIn('id', $records['invoice_payos_checkouts'])
            ->where(fn ($query) => $query->where('status', 'review')->orWhere(fn ($pending) => $pending
                ->where('status', 'pending')->where('expires_at', '>=', now()->subHours(2))))
            ->orderBy('id')->lockForUpdate()->first(['id']) !== null;
    }

    private function purgeConfirmation(int $jobId, array $records): string
    {
        return hash_hmac('sha256', json_encode(['purge', $jobId, app(PropertyContext::class)->id(), $records], JSON_THROW_ON_ERROR),
            config('app.key'));
    }

    private function assertPurgeReceipt(int $jobId, string $confirmation): void
    {
        $receipt = DB::table('scheduled_deletion_receipts')->where('job_id', $jobId)
            ->where('property_id', app(PropertyContext::class)->id())->lockForUpdate()->first();
        abort_unless($receipt !== null && hash_equals($receipt->confirmation, $confirmation), 404, self::JOB_MISSING);
    }

    public function impact(array $records): array
    {
        $impact = [];
        foreach (Arr::only($records, array_keys(DeletionPlan::LABELS)) as $table => $ids) {
            $impact[] = ['label' => DeletionPlan::LABELS[$table], 'count' => count($ids)];
        }

        return $impact;
    }

    private function confirmedRecords(array $records): array
    {
        return Arr::only($records, [...array_keys(DeletionPlan::LABELS), 'invoice_payos_checkouts', 'invoice_payos_transfers']);
    }

    private function confirmation(string $type, int $id, array $records): string
    {
        return hash_hmac('sha256', json_encode([$type, $id, $this->confirmedRecords($records)], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
