<?php

namespace App\Services;

use App\Http\Requests\Owner\RecordBulkMeterReadingRequest;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\MeterReadingRequestRecord;
use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BulkMeterReadings
{
    public function query(array $filters): Builder
    {
        $month = $this->month($filters['billing_month']);
        $query = OccupancyService::query()->where('billing_method', 'metered')->where('effective_from', '<', $month->addMonth())
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', $month))
            ->whereHas('occupancy', function ($q) use ($month, $filters) {
                $q->where('started_at', '<', $month->addMonth())->where(fn ($active) => $active->whereNull('ended_at')->orWhere('ended_at', '>', $month));
                if (isset($filters['room_id'])) {
                    $q->where('room_id', $filters['room_id']);
                }
                if (filled($filters['q'] ?? null)) {
                    $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['q'])).'%';
                    $q->whereHas('room', fn ($room) => $room->whereRaw("room_number LIKE ? ESCAPE '!'", [$pattern]));
                }
            })->with(['occupancy.room', 'occupancy.rentalGroup', 'service'])->orderBy('occupancy_id')->orderBy('service_id')->orderBy('effective_from')->orderBy('id');
        if (isset($filters['occupancy_service_id'])) {
            $query->whereKey($filters['occupancy_service_id']);
        }

        return $query;
    }

    public function describe(OccupancyService $service, string $monthName, ?Collection $periods = null, ?Collection $readings = null, ?Invoice $invoice = null): array
    {
        $occupancy = $service->occupancy;
        $periods ??= $occupancy->occupancyServices()->where('service_id', $service->service_id)->orderBy('id')->get();
        $readings ??= MeterReading::query()->whereIn('occupancy_service_id', $periods->modelKeys())->orderBy('period_start')->orderBy('period_end')->orderBy('id')->get();
        $month = $this->month($monthName);
        $invoice ??= Invoice::query()->where('occupancy_id', $occupancy->id)->whereDate('billing_month', $month->format('Y-m-d'))->first();
        $start = $month->max(CarbonImmutable::instance($occupancy->started_at))->max(CarbonImmutable::instance($service->effective_from));
        $end = $month->addMonth()->min(now()->toImmutable()->startOfSecond());
        foreach ([$occupancy->ended_at, $service->effective_until] as $limit) {
            if ($limit !== null) {
                $end = $end->min(CarbonImmutable::instance($limit));
            }
        }
        $latest = $readings->last();
        $previous = null;
        $blockers = [];
        if ($invoice !== null) {
            $blockers[] = 'Tháng này đã phát hành hóa đơn; chỉ số đã khóa.';
        }
        if (($priorPriceBlocker = $this->priorPriceBlocker($service, $periods, $readings)) !== null) {
            $blockers[] = $priorPriceBlocker;
        }
        if ($month->isFuture()) {
            $blockers[] = 'Chưa thể ghi chỉ số cho tháng ở tương lai.';
        }
        if ($latest !== null) {
            $same = $latest->occupancy_service_id === $service->id;
            $continuous = $same || $service->effective_from->equalTo($latest->period_end);
            if ($continuous) {
                $start = CarbonImmutable::instance($latest->period_end);
                $previous = $latest->current_reading;
                if ($start->lessThan($month)) {
                    $blockers[] = 'Còn thiếu kỳ chỉ số trước tháng đã chọn. Mở đợt thuê để bổ sung theo thứ tự.';
                }
            } elseif ($latest->period_end->greaterThan($start)) {
                $blockers[] = 'Đã có kỳ chỉ số mới hơn khoảng giá này. Hãy đối chiếu lịch sử trước.';
            } elseif ($latest->period_end->equalTo($start)) {
                $previous = $latest->current_reading;
            }
        }
        if ($start->greaterThanOrEqualTo($month->addMonth()) || $start->greaterThanOrEqualTo($end)) {
            $blockers[] = 'Khoảng này đã ghi đủ hoặc chưa có khoảng thời gian mới để ghi.';
        }
        if (($service->effective_until !== null && $start->greaterThanOrEqualTo($service->effective_until))
            || ($occupancy->ended_at !== null && $start->greaterThanOrEqualTo($occupancy->ended_at))) {
            $blockers[] = 'Đã ghi hết khoảng áp dụng dịch vụ hoặc thời gian thuê.';
        }
        $token = $this->token($service, $monthName, $periods, $readings, $invoice);

        return ['row_key' => (string) $service->id, 'billing_month' => $monthName, 'occupancy_id' => $occupancy->id,
            'occupancy_service_id' => $service->id, 'room' => $occupancy->room->only(['id', 'room_number']),
            'group' => $occupancy->rentalGroup->only(['id', 'group_code']),
            'service' => $service->service->only(['id', 'service_code', 'name']) + ['unit' => $service->unit],
            'unit' => $service->unit, 'unit_price' => $service->unit_price,
            'effective_from' => $service->effective_from->format('Y-m-d H:i:s'), 'effective_until' => $service->effective_until?->format('Y-m-d H:i:s'),
            'occupancy_started_at' => $occupancy->started_at->format('Y-m-d H:i:s'), 'occupancy_ended_at' => $occupancy->ended_at?->format('Y-m-d H:i:s'),
            'period_start' => $start->format('Y-m-d H:i:s'), 'period_end' => $end->format('Y-m-d H:i:s'),
            'previous_reading' => $previous, 'baseline_known' => $previous !== null, 'previous_reading_locked' => $previous !== null,
            'current_reading' => null, 'can_record' => $blockers === [], 'blocked_reason' => $blockers[0] ?? null, 'blockers' => array_values(array_unique($blockers)),
            'context_token' => $token, 'latest_reading_id' => $latest?->id, 'latest_reading' => $latest === null ? null : $this->readingState($latest),
            'month_readings' => $readings->filter(fn ($reading) => $reading->occupancy_service_id === $service->id && $reading->period_start->format('Y-m') === $monthName)->map(fn ($reading) => $this->readingState($reading))->values()->all(),
            'invoice_id' => $invoice?->id];
    }

    public function record(array $data, User $actor): array
    {
        $request = new RecordBulkMeterReadingRequest;
        $data = Validator::make($data, $request->rules(), $request->messages(), $request->attributes())->validate();
        $values = ['billing_month' => $data['billing_month'], 'occupancy_id' => (int) $data['occupancy_id'], 'occupancy_service_id' => (int) $data['occupancy_service_id'],
            'period_start' => $data['period_start'], 'period_end' => $data['period_end'],
            'previous_reading' => (string) BigDecimal::of((string) $data['previous_reading'])->toScale(3),
            'current_reading' => (string) BigDecimal::of((string) $data['current_reading'])->toScale(3), 'context_token' => $data['context_token']];
        $fingerprint = hash('sha256', json_encode([$actor->id, $data['idempotency_key'], $values], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $operation = function () use ($values, $actor, $data, $fingerprint) {
            $currentActor = User::query()->findOrFail($actor->id);
            abort_unless($currentActor->is_active && $currentActor->hasRole(User::ROLE_OWNER), 403);
            $manager = app(MeterReadingManager::class);
            $occupancy = Occupancy::query()->findOrFail($values['occupancy_id']);
            [$locked, $service, $readings, $periods] = $manager->lockContext($occupancy, $values['occupancy_service_id']);
            $prior = MeterReadingRequestRecord::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($prior !== null) {
                return $this->replay($prior, $fingerprint);
            }
            $invoice = Invoice::query()->where('occupancy_id', $locked->id)->whereDate('billing_month', $this->month($values['billing_month'])->format('Y-m-d'))->lockForUpdate()->first();
            $service->setRelation('occupancy', $locked);
            abort_unless(hash_equals($this->token($service, $values['billing_month'], $periods, $readings, $invoice), $values['context_token']), 409,
                'Dữ liệu phòng, khoảng giá hoặc chỉ số đã thay đổi. Tải lại dòng trước khi ghi.');
            if (($priorPriceBlocker = $this->priorPriceBlocker($service, $periods, $readings)) !== null) {
                abort(409, $priorPriceBlocker);
            }
            $month = $this->month($values['billing_month']);
            if (substr($values['period_start'], 0, 7) !== $values['billing_month'] || CarbonImmutable::parse($values['period_end'], config('app.timezone'))->greaterThan($month->addMonth())) {
                throw ValidationException::withMessages(['period_start' => 'Kỳ chỉ số phải nằm trong tháng đã chọn; cuối kỳ có thể đúng đầu tháng sau.']);
            }
            $reading = $manager->create($locked, $values, $actor->id);
            $record = MeterReadingRequestRecord::create(['idempotency_key' => $data['idempotency_key'], 'occupancy_id' => $locked->id,
                'occupancy_service_id' => $service->id, 'meter_reading_id' => $reading->id, 'original_reading_id' => $reading->id,
                'recorded_by' => $actor->id, 'request_data' => $values, 'request_fingerprint' => $fingerprint]);

            return $this->result($record, false);
        };
        try {
            return DB::transaction($operation, 3);
        } catch (UniqueConstraintViolationException) {
            return DB::transaction(function () use ($actor, $data, $fingerprint) {
                $currentActor = User::query()->findOrFail($actor->id);
                abort_unless($currentActor->is_active && $currentActor->hasRole(User::ROLE_OWNER), 403);
                $prior = MeterReadingRequestRecord::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
                abort_if($prior === null, 409, 'Yêu cầu chỉ số bị trùng. Hãy tải lại để đối chiếu.');

                return $this->replay($prior, $fingerprint);
            }, 3);
        }
    }

    private function replay(MeterReadingRequestRecord $record, string $fingerprint): array
    {
        abort_unless(hash_equals($record->request_fingerprint, $fingerprint), 409, 'Mã yêu cầu đã được dùng cho dòng chỉ số khác. Giữ nguyên nội dung khi gửi lại.');

        return $this->result($record, true);
    }

    private function result(MeterReadingRequestRecord $record, bool $replayed): array
    {
        $reading = $record->meter_reading_id === null ? null : MeterReading::query()->with('occupancyService.service', 'invoiceItem:id,meter_reading_id,invoice_id')->find($record->meter_reading_id);
        $changed = false;
        if ($reading !== null) {
            foreach (['occupancy_service_id', 'period_start', 'period_end', 'previous_reading', 'current_reading'] as $field) {
                $value = in_array($field, ['period_start', 'period_end'], true) ? $reading->{$field}->format('Y-m-d H:i:s') : $reading->{$field};
                if ((string) $value !== (string) $record->request_data[$field]) {
                    $changed = true;
                }
            }
        }

        return ['reading' => $reading, 'reading_id' => $record->original_reading_id, 'reading_deleted' => $reading === null,
            'reading_changed' => $changed, 'replayed' => $replayed];
    }

    private function token(OccupancyService $service, string $month, Collection $periods, Collection $readings, ?Invoice $invoice): string
    {
        $occupancy = $service->occupancy;

        return hash_hmac('sha256', json_encode(['bulk-meter', $month, $service->id,
            [$occupancy->id, $occupancy->room_id, $occupancy->rental_group_id, $occupancy->started_at->format('Y-m-d H:i:s'), $occupancy->ended_at?->format('Y-m-d H:i:s')],
            $periods->map(fn ($period) => [$period->id, $period->service_id, $period->effective_from->format('Y-m-d H:i:s'), $period->effective_until?->format('Y-m-d H:i:s'), $period->billing_method, $period->unit_price, $period->unit])->all(),
            $readings->map(fn ($reading) => $this->readingState($reading))->all(), $invoice === null ? null : [$invoice->id, $invoice->revision]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), config('app.key'));
    }

    private function priorPriceBlocker(OccupancyService $service, Collection $periods, Collection $readings): ?string
    {
        foreach ($periods as $prior) {
            if ($prior->id === $service->id || $prior->effective_until === null
                || ! $prior->effective_from->lessThan($service->effective_from)
                || ! $prior->effective_until->equalTo($service->effective_from)) {
                continue;
            }
            $completed = $readings->contains(fn ($reading) => $reading->occupancy_service_id === $prior->id
                && $reading->period_end->equalTo($service->effective_from));
            if (! $completed) {
                return 'Khoảng giá liền trước chưa ghi đủ chỉ số đến thời điểm đổi giá. Hãy ghi khoảng trước rồi tải lại dòng này.';
            }
        }

        return null;
    }

    private function readingState(MeterReading $reading): array
    {
        return ['id' => $reading->id, 'occupancy_service_id' => $reading->occupancy_service_id,
            'period_start' => $reading->period_start->format('Y-m-d H:i:s'), 'period_end' => $reading->period_end->format('Y-m-d H:i:s'),
            'previous_reading' => $reading->previous_reading, 'current_reading' => $reading->current_reading];
    }

    private function month(string $name): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $name, config('app.timezone'));
    }
}
