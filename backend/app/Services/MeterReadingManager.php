<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\Occupancy;
use App\Models\OccupancyService;
use App\Models\RentalGroup;
use App\Models\Room;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeterReadingManager
{
    public function create(Occupancy $occupancy, array $data, int $actorId): MeterReading
    {
        return $this->transaction(function () use ($occupancy, $data, $actorId) {
            [$locked, $service, $readings] = $this->lockContext($occupancy, (int) $data['occupancy_service_id']);
            $values = $this->validatePeriod($locked, $service, $data);
            $this->assertMonthOpen($locked, $values['period_start']);
            $this->assertAppend($service, $readings, $values);

            return MeterReading::create($values + ['occupancy_service_id' => $service->id, 'recorded_by' => $actorId])->refresh();
        });
    }

    public function update(Occupancy $occupancy, MeterReading $reading, array $data): MeterReading
    {
        return $this->transaction(function () use ($occupancy, $reading, $data) {
            [$locked, $service, $readings] = $this->lockContext($occupancy, $reading->occupancy_service_id);
            $record = $readings->firstWhere('id', $reading->id);
            abort_if($record === null, 404);
            if ((int) $data['occupancy_service_id'] !== $service->id) {
                throw ValidationException::withMessages(['occupancy_service_id' => 'Không thể chuyển chỉ số sang khoảng áp dụng dịch vụ khác.']);
            }
            $this->assertMutable($locked, $record, $readings);
            $values = $this->validatePeriod($locked, $service, $data);
            $this->assertMonthOpen($locked, $values['period_start']);
            $this->assertAppend($service, $readings->where('id', '!=', $record->id)->values(), $values);
            $record->update($values);

            return $record->refresh();
        });
    }

    public function delete(Occupancy $occupancy, MeterReading $reading): void
    {
        $this->transaction(function () use ($occupancy, $reading) {
            [$locked, , $readings] = $this->lockContext($occupancy, $reading->occupancy_service_id);
            $record = $readings->firstWhere('id', $reading->id);
            abort_if($record === null, 404);
            $this->assertMutable($locked, $record, $readings);
            $record->delete();
        });
    }

    public function lockContext(Occupancy $occupancy, int $serviceId): array
    {
        Room::query()->lockForUpdate()->findOrFail($occupancy->room_id);
        RentalGroup::query()->lockForUpdate()->findOrFail($occupancy->rental_group_id);
        $locked = Occupancy::query()->lockForUpdate()->findOrFail($occupancy->id);
        $period = $locked->occupancyServices()->whereKey($serviceId)->firstOrFail();
        $periods = $locked->occupancyServices()->where('service_id', $period->service_id)->orderBy('id')->lockForUpdate()->get();
        $service = $periods->firstWhere('id', $serviceId);
        $readings = MeterReading::query()->whereIn('occupancy_service_id', $periods->modelKeys())
            ->orderBy('period_start')->orderBy('period_end')->orderBy('id')->lockForUpdate()->get();

        return [$locked, $service, $readings, $periods];
    }

    private function validatePeriod(Occupancy $occupancy, OccupancyService $service, array $data): array
    {
        if ($service->billing_method !== 'metered') {
            throw ValidationException::withMessages(['occupancy_service_id' => 'Chỉ ghi chỉ số cho dịch vụ tính theo tiêu thụ.']);
        }
        $start = $this->date($data['period_start'], 'period_start');
        $end = $this->date($data['period_end'], 'period_end');
        if ($end->lessThanOrEqualTo($start) || $end->isFuture()) {
            throw ValidationException::withMessages(['period_end' => 'Cuối kỳ phải sau đầu kỳ và không ở tương lai.']);
        }
        if ($start->lessThan($occupancy->started_at) || $start->lessThan($service->effective_from)) {
            throw ValidationException::withMessages(['period_start' => 'Đầu kỳ phải nằm trong thời gian thuê và áp dụng dịch vụ.']);
        }
        if (($occupancy->ended_at !== null && $end->greaterThan($occupancy->ended_at)) ||
            ($service->effective_until !== null && $end->greaterThan($service->effective_until))) {
            throw ValidationException::withMessages(['period_end' => 'Cuối kỳ vượt quá thời điểm kết thúc thuê hoặc ngừng khoảng dịch vụ.']);
        }
        if ($end->greaterThan($start->startOfMonth()->addMonth())) {
            throw ValidationException::withMessages(['period_end' => 'Mỗi kỳ chỉ số phải nằm trong một tháng tính tiền; có thể kết thúc đúng đầu tháng sau.']);
        }
        $previous = $this->decimal($data['previous_reading'], 'previous_reading');
        $current = $this->decimal($data['current_reading'], 'current_reading');
        if ($current->isLessThan($previous)) {
            throw ValidationException::withMessages(['current_reading' => 'Chỉ số cuối kỳ không được nhỏ hơn chỉ số đầu kỳ.']);
        }

        return ['period_start' => $start, 'period_end' => $end, 'previous_reading' => (string) $previous, 'current_reading' => (string) $current];
    }

    private function assertAppend(OccupancyService $service, Collection $readings, array $values): void
    {
        $latest = $readings->last();
        if ($latest === null) {
            return;
        }
        $start = $values['period_start'];
        $samePeriod = $latest->occupancy_service_id === $service->id;
        if ($start->lessThan($latest->period_end)) {
            throw ValidationException::withMessages(['period_start' => 'Kỳ mới không được trùng hoặc nằm trước các kỳ chỉ số đã ghi.']);
        }
        // Adjacent price versions use the same meter within this occupancy.
        $continuous = $samePeriod || $service->effective_from->equalTo($latest->period_end);
        if ($continuous && ! $start->equalTo($latest->period_end)) {
            throw ValidationException::withMessages(['period_start' => 'Đầu kỳ mới phải đúng bằng cuối kỳ đã ghi trước đó.']);
        }
        if (($continuous || $start->equalTo($latest->period_end)) &&
            ! BigDecimal::of($values['previous_reading'])->isEqualTo($latest->current_reading)) {
            throw ValidationException::withMessages(['previous_reading' => 'Chỉ số đầu kỳ phải bằng chỉ số cuối kỳ trước đó của cùng đồng hồ.']);
        }
    }

    private function assertMutable(Occupancy $occupancy, MeterReading $record, Collection $readings): void
    {
        abort_unless($readings->last()?->id === $record->id, 409, 'Chỉ được sửa hoặc xóa kỳ cuối cùng của dịch vụ để giữ liên tục chỉ số.');
        abort_if(InvoiceItem::query()->where('meter_reading_id', $record->id)->lockForUpdate()->first() !== null, 409, 'Chỉ số đã đưa vào hóa đơn, không thể sửa hoặc xóa.');
        $this->assertMonthOpen($occupancy, CarbonImmutable::instance($record->period_start));
    }

    private function assertMonthOpen(Occupancy $occupancy, CarbonImmutable $start): void
    {
        $invoice = Invoice::query()->where('occupancy_id', $occupancy->id)
            ->whereDate('billing_month', $start->startOfMonth()->format('Y-m-d'))->lockForUpdate()->first();
        abort_if($invoice !== null, 409, 'Tháng này đã phát hành hóa đơn; không thể thêm, sửa hoặc xóa chỉ số.');
    }

    private function date(string $value, string $field): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $value, config('app.timezone'));
        } catch (\Exception) {
            throw ValidationException::withMessages([$field => 'Thời điểm cần có định dạng năm-tháng-ngày giờ:phút:giây.']);
        }
        if ($date->format('Y-m-d H:i:s') !== $value || $date->year < 1000) {
            throw ValidationException::withMessages([$field => 'Thời điểm không hợp lệ.']);
        }

        return $date;
    }

    private function decimal(mixed $value, string $field): BigDecimal
    {
        if ((! is_string($value) && ! is_int($value) && ! is_float($value)) || ! preg_match('/^\d{1,12}(?:\.\d{1,3})?$/D', (string) $value)) {
            throw ValidationException::withMessages([$field => 'Chỉ số phải là số không âm, tối đa 12 chữ số nguyên và 3 chữ số thập phân.']);
        }

        return BigDecimal::of((string) $value)->toScale(3);
    }

    private function transaction(Closure $callback): mixed
    {
        try {
            return DB::transaction($callback, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Kỳ chỉ số vừa thay đổi hoặc trùng kỳ đã ghi. Vui lòng tải lại và thử lại.');
        }
    }
}
